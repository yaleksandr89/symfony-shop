<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use App\Utils\Manager\ProductImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[Group(name: 'unit')]
final class ProductImageManagerTest extends TestCase
{
    #[TestDox('Ошибка сохранения БД оставляет все физические варианты изображения без изменений')]
    public function testKeepsPhysicalFilesWhenFlushFails(): void
    {
        $filesystem = new Filesystem();
        $productImageDir = $this->temporaryDirectory();

        try {
            [$product, $productImage, $filePaths] = $this->createImageFixture($filesystem, $productImageDir);
            $flushException = new RuntimeException('Flush failed.');
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager->expects(self::once())->method('flush')->willThrowException($flushException);

            try {
                $this->manager($entityManager, $filesystem)->removeImageFromProduct($productImage, $productImageDir);
                self::fail('A failed flush must propagate its exception.');
            } catch (RuntimeException $exception) {
                self::assertSame($flushException, $exception);
            }

            foreach ($filePaths as $filePath) {
                self::assertFileExists($filePath);
            }
            self::assertDirectoryExists($productImageDir);
        } finally {
            $filesystem->remove($productImageDir);
        }
    }

    #[TestDox('Успешное удаление сначала сохраняет изменение связи в БД, затем очищает все файлы и пустой каталог')]
    public function testRemovesPhysicalFilesAfterSuccessfulFlush(): void
    {
        $filesystem = new Filesystem();
        $productImageDir = $this->temporaryDirectory();

        try {
            [$product, $productImage, $filePaths] = $this->createImageFixture($filesystem, $productImageDir);
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager
                ->expects(self::once())
                ->method('flush')
                ->willReturnCallback(static function () use ($product, $productImage, $filePaths, $productImageDir): void {
                    self::assertFalse($product->getProductImages()->contains($productImage));
                    self::assertNull($productImage->getProduct());
                    foreach ($filePaths as $filePath) {
                        self::assertFileExists($filePath);
                    }
                    self::assertDirectoryExists($productImageDir);
                });

            $this->manager($entityManager, $filesystem)->removeImageFromProduct($productImage, $productImageDir);

            self::assertFalse($product->getProductImages()->contains($productImage));
            self::assertNull($productImage->getProduct());
            foreach ($filePaths as $filePath) {
                self::assertFileDoesNotExist($filePath);
            }
            self::assertDirectoryDoesNotExist($productImageDir);
        } finally {
            $filesystem->remove($productImageDir);
        }
    }

    /** @return array{Product, ProductImage, list<string>} */
    private function createImageFixture(Filesystem $filesystem, string $productImageDir): array
    {
        $filenames = ['image-small.jpg', 'image-middle.jpg', 'image-big.jpg'];
        $filesystem->mkdir($productImageDir);

        $filePaths = [];
        foreach ($filenames as $filename) {
            $filePath = $productImageDir.DIRECTORY_SEPARATOR.$filename;
            $filesystem->dumpFile($filePath, 'image');
            $filePaths[] = $filePath;
        }

        $productImage = (new ProductImage())
            ->setFilenameSmall($filenames[0])
            ->setFilenameMiddle($filenames[1])
            ->setFilenameBig($filenames[2]);
        $product = new Product();
        $product->addProductImage($productImage);

        return [$product, $productImage, $filePaths];
    }

    private function manager(EntityManagerInterface $entityManager, Filesystem $filesystem): ProductImageManager
    {
        $filesystemWorker = new FilesystemWorker($filesystem);

        return new ProductImageManager(
            $entityManager,
            $filesystemWorker,
            sys_get_temp_dir(),
            new ImageResizer($this->createStub(Imagine::class), $filesystemWorker),
        );
    }

    private function temporaryDirectory(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'product-image-manager-'.bin2hex(random_bytes(8));
    }
}
