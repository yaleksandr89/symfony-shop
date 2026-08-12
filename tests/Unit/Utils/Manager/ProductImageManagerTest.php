<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use App\Utils\Manager\ProductImageManager;
use App\Utils\Manager\ProductManager;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[Group(name: 'unit')]
final class ProductImageManagerTest extends TestCase
{
    #[TestDox('Ошибка middle-варианта удаляет small и частичный middle, не затрагивая unrelated-файл')]
    public function testMiddleFailureCompensatesOwnedVariantsAndKeepsUnrelatedFile(): void
    {
        $this->assertVariantFailureCompensation(2);
    }

    #[TestDox('Ошибка big-варианта удаляет small, middle и частичный big, не затрагивая unrelated-файл')]
    public function testBigFailureCompensatesOwnedVariantsAndKeepsUnrelatedFile(): void
    {
        $this->assertVariantFailureCompensation(3);
    }

    #[TestDox('Успешное создание сохраняет три варианта с общим случайным stem и не перезаписывает unrelated-файл')]
    public function testSuccessfulCreationWritesThreeVariantsWithSharedStem(): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/uploads';
        $productImageDir = $root.'/products/1';
        $filesystem->mkdir([$uploadsTempDir, $productImageDir]);
        $this->writePng($uploadsTempDir.'/source.png');
        $unrelatedPath = $productImageDir.'/unrelated.jpg';
        $filesystem->dumpFile($unrelatedPath, 'unrelated');

        try {
            $entityManager = $this->createStub(EntityManagerInterface::class);
            $manager = $this->manager(
                $entityManager,
                $filesystem,
                $uploadsTempDir,
                $this->writingImageResizer($filesystem),
            );

            $productImage = $manager->saveImageForProduct($productImageDir, 'source.png');
            self::assertInstanceOf(ProductImage::class, $productImage);
            $filenames = [
                $productImage->getFilenameSmall(),
                $productImage->getFilenameMiddle(),
                $productImage->getFilenameBig(),
            ];

            self::assertCount(3, array_unique($filenames));
            foreach ($filenames as $filename) {
                self::assertIsString($filename);
                self::assertMatchesRegularExpression('/^[a-f0-9]{32}_(small|middle|big)\.jpg$/', $filename);
                self::assertFileExists($productImageDir.'/'.$filename);
            }
            self::assertSame(
                [preg_replace('/_(small|middle|big)\.jpg$/', '', (string) $filenames[0])],
                array_values(array_unique(array_map(
                    static fn (?string $filename): ?string => preg_replace('/_(small|middle|big)\.jpg$/', '', (string) $filename),
                    $filenames,
                ))),
            );
            self::assertSame('unrelated', file_get_contents($unrelatedPath));
            self::assertCount(4, glob($productImageDir.'/*') ?: []);
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Ошибка transaction commit компенсирует полную тройку файлов и сохраняет исходное исключение')]
    public function testTransactionFailureCompensatesCompleteTupleAndPreservesPrimaryException(): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/uploads';
        $productImagesRoot = $root.'/products';
        $productImageDir = $productImagesRoot.'/42';
        $filesystem->mkdir([$uploadsTempDir, $productImageDir]);
        $this->writePng($uploadsTempDir.'/source.png');
        $unrelatedPath = $productImageDir.'/unrelated.jpg';
        $filesystem->dumpFile($unrelatedPath, 'unrelated');
        $transactionFailure = new RuntimeException('Transaction commit failed.');
        $product = new class extends Product {
            public function getId(): ?int
            {
                return 42;
            }
        };

        try {
            $entityManager = $this->createMock(EntityManagerInterface::class);
            $entityManager->expects(self::once())->method('persist')->with($product);
            $entityManager
                ->expects(self::once())
                ->method('wrapInTransaction')
                ->willReturnCallback(function (callable $callback) use ($entityManager, $transactionFailure, $productImageDir): never {
                    $callback($entityManager);
                    self::assertCount(4, glob($productImageDir.'/*') ?: []);

                    throw $transactionFailure;
                });
            $productImageManager = $this->manager(
                $entityManager,
                $filesystem,
                $uploadsTempDir,
                $this->writingImageResizer($filesystem),
            );
            $productManager = new ProductManager($entityManager, $productImagesRoot, $productImageManager);

            try {
                $productManager->saveProduct($product, 'source.png');
                self::fail('A transaction failure must propagate.');
            } catch (RuntimeException $exception) {
                self::assertSame($transactionFailure, $exception);
            }

            self::assertCount(1, $product->getProductImages());
            $createdImage = $product->getProductImages()->first();
            self::assertInstanceOf(ProductImage::class, $createdImage);
            self::assertFileDoesNotExist($productImageDir.'/'.(string) $createdImage->getFilenameSmall());
            self::assertFileDoesNotExist($productImageDir.'/'.(string) $createdImage->getFilenameMiddle());
            self::assertFileDoesNotExist($productImageDir.'/'.(string) $createdImage->getFilenameBig());
            self::assertSame('unrelated', file_get_contents($unrelatedPath));
        } finally {
            $filesystem->remove($root);
        }
    }

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

    private function assertVariantFailureCompensation(int $failedVariant): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/uploads';
        $productImageDir = $root.'/products/1';
        $filesystem->mkdir([$uploadsTempDir, $productImageDir]);
        $this->writePng($uploadsTempDir.'/source.png');
        $unrelatedPath = $productImageDir.'/unrelated.jpg';
        $filesystem->dumpFile($unrelatedPath, 'unrelated');
        $resizeFailure = new RuntimeException('Resize failed.');

        try {
            $manager = $this->manager(
                $this->createStub(EntityManagerInterface::class),
                $filesystem,
                $uploadsTempDir,
                $this->writingImageResizer($filesystem, $failedVariant, $resizeFailure),
            );

            try {
                $manager->saveImageForProduct($productImageDir, 'source.png');
                self::fail('A resize failure must propagate.');
            } catch (RuntimeException $exception) {
                self::assertSame($resizeFailure, $exception);
            }

            self::assertSame('unrelated', file_get_contents($unrelatedPath));
            self::assertSame([$unrelatedPath], glob($productImageDir.'/*') ?: []);
        } finally {
            $filesystem->remove($root);
        }
    }

    private function manager(
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        ?string $uploadsTempDir = null,
        ?ImageResizer $imageResizer = null,
    ): ProductImageManager
    {
        $filesystemWorker = new FilesystemWorker($filesystem);

        return new ProductImageManager(
            $entityManager,
            $filesystemWorker,
            $uploadsTempDir ?? sys_get_temp_dir(),
            $imageResizer ?? new ImageResizer($this->createStub(Imagine::class), $filesystemWorker),
            new NullLogger(),
        );
    }

    private function writingImageResizer(
        Filesystem $filesystem,
        ?int $failedVariant = null,
        ?RuntimeException $failure = null,
    ): ImageResizer {
        $saveCount = 0;
        $image = $this->createStub(ImageInterface::class);
        $image->method('resize')->willReturnSelf();
        $image
            ->method('save')
            ->willReturnCallback(function (string $path) use ($filesystem, &$saveCount, $failedVariant, $failure, $image): ImageInterface {
                ++$saveCount;
                $filesystem->dumpFile($path, 'variant '.$saveCount);
                if ($saveCount === $failedVariant) {
                    throw $failure ?? new RuntimeException('Resize failed.');
                }

                return $image;
            });
        $imagine = $this->createStub(Imagine::class);
        $imagine->method('open')->willReturn($image);

        return new ImageResizer($imagine, new FilesystemWorker($filesystem));
    }

    private function writePng(string $path): void
    {
        $image = imagecreatetruecolor(20, 10);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertTrue(imagepng($image, $path));
    }

    private function temporaryDirectory(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'product-image-manager-'.bin2hex(random_bytes(8));
    }
}
