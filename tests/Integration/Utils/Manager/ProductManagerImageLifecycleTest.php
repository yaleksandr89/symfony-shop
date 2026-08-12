<?php

declare(strict_types=1);

namespace App\Tests\Integration\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Form\DTO\EditProductModel;
use App\Form\Handler\ProductFormHandler;
use App\Utils\File\FileSaver;
use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use App\Utils\Manager\ProductImageManager;
use App\Utils\Manager\ProductManager;
use Doctrine\ORM\EntityManagerInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\ImageInterface;
use Knp\Component\Pager\PaginatorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Psr\Log\NullLogger;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Group(name: 'integration')]
final class ProductManagerImageLifecycleTest extends KernelTestCase
{
    #[TestDox('Новый товар в SQLite получает numeric ID до записи трёх изображений в свой каталог')]
    public function testNewProductGetsNumericIdBeforeImageVariantsAreWritten(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/uploads';
        $productImagesRoot = $root.'/products';
        $filesystem->mkdir([$uploadsTempDir, $productImagesRoot]);
        $this->writePng($uploadsTempDir.'/source.png');
        $product = $this->newProduct('New image lifecycle product');
        self::assertNull($product->getId());

        try {
            $manager = $this->manager($entityManager, $filesystem, $uploadsTempDir, $productImagesRoot);
            $manager->saveProduct($product, 'source.png');

            $productId = $product->getId();
            self::assertIsInt($productId);
            $productImageDir = $productImagesRoot.'/'.$productId;
            self::assertDirectoryExists($productImageDir);
            self::assertSame([], glob($productImagesRoot.'/*.jpg') ?: []);
            self::assertCount(3, glob($productImageDir.'/*.jpg') ?: []);
            self::assertCount(1, $product->getProductImages());

            $productImage = $product->getProductImages()->first();
            self::assertInstanceOf(ProductImage::class, $productImage);
            $this->assertPersistedFilenamesExist($productImage, $productImageDir);

            $entityManager->clear();
            $persistedProduct = $entityManager->find(Product::class, $productId);
            self::assertInstanceOf(Product::class, $persistedProduct);
            self::assertCount(1, $persistedProduct->getProductImages());
            self::assertInstanceOf(ProductImage::class, $persistedProduct->getProductImages()->first());
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Существующий товар сохраняет новое изображение в каталоге своего стабильного numeric ID')]
    public function testExistingProductUsesStableNumericIdDirectory(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/uploads';
        $productImagesRoot = $root.'/products';
        $filesystem->mkdir([$uploadsTempDir, $productImagesRoot]);
        $this->writePng($uploadsTempDir.'/source.png');
        $product = $this->newProduct('Existing image lifecycle product');
        $entityManager->persist($product);
        $entityManager->flush();
        $productId = $product->getId();
        self::assertIsInt($productId);

        try {
            $manager = $this->manager($entityManager, $filesystem, $uploadsTempDir, $productImagesRoot);
            $manager->saveProduct($product, 'source.png');

            self::assertSame($productId, $product->getId());
            $productImageDir = $productImagesRoot.'/'.$productId;
            self::assertDirectoryExists($productImageDir);
            self::assertCount(3, glob($productImageDir.'/*.jpg') ?: []);
            self::assertCount(1, $product->getProductImages());
            $productImage = $product->getProductImages()->first();
            self::assertInstanceOf(ProductImage::class, $productImage);
            $this->assertPersistedFilenamesExist($productImage, $productImageDir);
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Успешное сохранение через обработчик удаляет staged-файл и пустой temp-каталог после commit')]
    public function testSuccessfulFormHandlerSaveCleansStagedUploadAfterCommit(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $sourceDir = $root.'/source';
        $uploadsTempDir = $root.'/uploads';
        $productImagesRoot = $root.'/products';
        $filesystem->mkdir([$sourceDir, $productImagesRoot]);
        $sourcePath = $sourceDir.'/source.png';
        $this->writePng($sourcePath);
        $uploadedFile = new UploadedFile($sourcePath, 'catalog.png', 'image/png', null, true);
        $imageField = $this->createStub(FormInterface::class);
        $imageField->method('getData')->willReturn($uploadedFile);
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('newImage')->willReturn($imageField);
        $productManager = $this->manager($entityManager, $filesystem, $uploadsTempDir, $productImagesRoot);
        $handler = new ProductFormHandler(
            $productManager,
            new FileSaver(new AsciiSlugger(), new FilesystemWorker($filesystem), $uploadsTempDir),
            new FilesystemWorker($filesystem),
            $this->createStub(PaginatorInterface::class),
            $this->createStub(FilterBuilderUpdater::class),
            new NullLogger(),
        );
        $model = new EditProductModel(
            title: 'Handler image lifecycle product '.bin2hex(random_bytes(8)),
            price: '24.99',
            quantity: 2,
            description: 'Handler integration lifecycle test.',
            isPublished: false,
            isDeleted: false,
            isNew: false,
            isOnSale: false,
        );

        try {
            $product = $handler->processEditForm($form, $model);

            self::assertIsInt($product->getId());
            self::assertDirectoryDoesNotExist($uploadsTempDir);
            self::assertCount(3, glob($productImagesRoot.'/'.$product->getId().'/*.jpg') ?: []);
            self::assertCount(1, $product->getProductImages());
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Ошибка temp-cleanup после commit не отменяет успешное сохранение товара и вариантов')]
    public function testPostCommitTempCleanupFailureDoesNotTurnSuccessIntoFailure(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $sourceDir = $root.'/source';
        $uploadsTempDir = $root.'/uploads';
        $productImagesRoot = $root.'/products';
        $filesystem->mkdir([$sourceDir, $productImagesRoot]);
        $sourcePath = $sourceDir.'/source.png';
        $this->writePng($sourcePath);
        $uploadedFile = new UploadedFile($sourcePath, 'catalog.png', 'image/png', null, true);
        $imageField = $this->createStub(FormInterface::class);
        $imageField->method('getData')->willReturn($uploadedFile);
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('get')->with('newImage')->willReturn($imageField);
        $saveCount = 0;
        $image = $this->createStub(ImageInterface::class);
        $image->method('resize')->willReturnSelf();
        $image
            ->method('save')
            ->willReturnCallback(function (string $path) use ($filesystem, $uploadsTempDir, &$saveCount, $image): ImageInterface {
                ++$saveCount;
                $filesystem->dumpFile($path, 'variant '.$saveCount);
                if (3 === $saveCount) {
                    self::assertTrue(chmod($uploadsTempDir, 0o555));
                }

                return $image;
            });
        $imagine = $this->createStub(Imagine::class);
        $imagine->method('open')->willReturn($image);
        $productManager = $this->manager($entityManager, $filesystem, $uploadsTempDir, $productImagesRoot, $imagine);
        $handler = new ProductFormHandler(
            $productManager,
            new FileSaver(new AsciiSlugger(), new FilesystemWorker($filesystem), $uploadsTempDir),
            new FilesystemWorker($filesystem),
            $this->createStub(PaginatorInterface::class),
            $this->createStub(FilterBuilderUpdater::class),
            new NullLogger(),
        );
        $model = new EditProductModel(
            title: 'Post-commit cleanup failure product '.bin2hex(random_bytes(8)),
            price: '29.99',
            quantity: 2,
            description: 'Post-commit cleanup failure test.',
            isPublished: false,
            isDeleted: false,
            isNew: false,
            isOnSale: false,
        );

        try {
            $product = $handler->processEditForm($form, $model);

            self::assertIsInt($product->getId());
            self::assertCount(1, glob($uploadsTempDir.'/*') ?: []);
            self::assertCount(3, glob($productImagesRoot.'/'.$product->getId().'/*.jpg') ?: []);
            self::assertCount(1, $product->getProductImages());
            self::assertNotNull($entityManager->find(Product::class, $product->getId()));
        } finally {
            if (is_dir($uploadsTempDir)) {
                chmod($uploadsTempDir, 0o755);
            }
            $filesystem->remove($root);
        }
    }

    private function manager(
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        string $uploadsTempDir,
        string $productImagesRoot,
        ?Imagine $imagine = null,
    ): ProductManager {
        $filesystemWorker = new FilesystemWorker($filesystem);
        $productImageManager = new ProductImageManager(
            $entityManager,
            $filesystemWorker,
            $uploadsTempDir,
            new ImageResizer($imagine ?? new Imagine(), $filesystemWorker),
            new NullLogger(),
        );

        return new ProductManager($entityManager, $productImagesRoot, $productImageManager);
    }

    private function newProduct(string $title): Product
    {
        return (new Product())
            ->setTitle($title.' '.bin2hex(random_bytes(8)))
            ->setPrice('19.99')
            ->setQuantity(3)
            ->setDescription('Integration image lifecycle test.');
    }

    private function assertPersistedFilenamesExist(ProductImage $productImage, string $productImageDir): void
    {
        foreach ([
            $productImage->getFilenameSmall(),
            $productImage->getFilenameMiddle(),
            $productImage->getFilenameBig(),
        ] as $filename) {
            self::assertIsString($filename);
            self::assertFileExists($productImageDir.'/'.$filename);
        }
    }

    private function writePng(string $path): void
    {
        $image = imagecreatetruecolor(100, 50);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertTrue(imagepng($image, $path));
    }

    private function temporaryDirectory(): string
    {
        return sys_get_temp_dir().'/product-manager-image-lifecycle-'.bin2hex(random_bytes(8));
    }
}
