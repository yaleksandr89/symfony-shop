<?php

declare(strict_types=1);

namespace App\Tests\Unit\Demo;

use App\Demo\DemoAssetInstaller;
use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

#[Group(name: 'unit')]
class DemoAssetInstallerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/demo-assets-'.uniqid('', true);
        mkdir($this->directory.'/fixtures/demo/images/demo-key', 0775, true);
        foreach (['big', 'middle', 'small'] as $variant) {
            file_put_contents($this->directory.'/fixtures/demo/images/demo-key/demo-key_'.$variant.'.jpg', 'fixture-'.$variant);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    #[TestDox('Отсутствующие цели копируются, а повторный запуск ничего не меняет')]
    public function testMissingTargetsAreCopiedAndSecondRunIsUnchanged(): void
    {
        $installer = $this->installer();
        $product = $this->product(101);

        $first = $installer->install($product, 'demo-key');
        self::assertSame('created', $first['record']);
        self::assertSame(['copied' => 3, 'updated' => 0, 'existing' => 0], $first['files']);
        $installer->commit();

        $second = $installer->install($product, 'demo-key');
        self::assertSame('existing', $second['record']);
        self::assertSame(['copied' => 0, 'updated' => 0, 'existing' => 3], $second['files']);
        $installer->commit();
    }

    #[TestDox('Отличающаяся цель обновляется и восстанавливается при откате')]
    public function testDifferentTargetIsUpdatedAndRollbackRestoresIt(): void
    {
        $installer = $this->installer();
        $target = $this->directory.'/uploads/102/demo-key_big.jpg';
        mkdir(dirname($target), 0775, true);
        file_put_contents($target, 'outdated');

        $result = $installer->install($this->product(102), 'demo-key');
        self::assertSame(1, $result['files']['updated']);
        self::assertSame('fixture-big', file_get_contents($target));
        $installer->rollback();
        self::assertSame('outdated', file_get_contents($target));
        self::assertFileDoesNotExist($this->directory.'/uploads/102/demo-key_middle.jpg');
    }

    #[TestDox('Один источник можно установить для разных идентификаторов товаров')]
    public function testSameSourceCanBeInstalledForDifferentProductIds(): void
    {
        $installer = $this->installer();
        $installer->install($this->product(103), 'demo-key');
        $installer->install($this->product(104), 'demo-key');

        self::assertFileExists($this->directory.'/uploads/103/demo-key_small.jpg');
        self::assertFileExists($this->directory.'/uploads/104/demo-key_small.jpg');
        $installer->rollback();
    }

    #[TestDox('Отсутствующий источник завершается ошибкой до изменения цели')]
    public function testMissingSourceFailsBeforeTargetMutation(): void
    {
        unlink($this->directory.'/fixtures/demo/images/demo-key/demo-key_middle.jpg');
        $installer = $this->installer();

        $this->expectException(\RuntimeException::class);
        try {
            $installer->assertSourcesExist([['slug' => 'demo-product', 'image_key' => 'demo-key']]);
        } finally {
            self::assertDirectoryDoesNotExist($this->directory.'/uploads');
        }
    }

    #[TestDox('Точный кортеж изображения товара не дублируется')]
    public function testExactProductImageTupleIsNotDuplicated(): void
    {
        $product = $this->product(105);
        $product->addProductImage((new ProductImage())->setFilenameBig('demo-key_big.jpg')->setFilenameMiddle('demo-key_middle.jpg')->setFilenameSmall('demo-key_small.jpg'));
        $installer = $this->installer();

        $result = $installer->install($product, 'demo-key');
        self::assertSame('existing', $result['record']);
        self::assertCount(1, $product->getProductImages());
        $installer->rollback();
    }

    private function installer(): DemoAssetInstaller
    {
        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->directory);

        return new DemoAssetInstaller($this->createStub(EntityManagerInterface::class), $kernel, $this->directory.'/uploads');
    }

    private function product(int $id): Product
    {
        $product = (new Product())->setSlug('demo-product-'.$id);
        $property = new \ReflectionProperty(Product::class, 'id');
        $property->setValue($product, $id);

        return $product;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }
            $path = $directory.'/'.$item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
