<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class ProductImageManager extends AbstractBaseManager
{
    public function __construct(
        protected EntityManagerInterface $em,
        private FilesystemWorker $filesystemWorker,
        private string $uploadsTempDir,
        private ImageResizer $imageResizer,
        private LoggerInterface $logger,
    ) {
        parent::__construct($em);
    }

    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(ProductImage::class);
    }

    public function saveImageForProduct(string $productDir, ?string $tempImageFilename = null): ?ProductImage
    {
        if (!$tempImageFilename) {
            return null;
        }

        $this->filesystemWorker->createFolderIfNotExist($productDir);
        $filenames = $this->generateVariantFilenames($productDir);
        $paths = array_map(
            fn (string $filename): string => $this->filesystemWorker->generatePathToFile($productDir, $filename),
            $filenames,
        );

        try {
            $imageSmall = $this->resizeVariant($productDir, $tempImageFilename, $filenames['small'], 60);
            $imageMiddle = $this->resizeVariant($productDir, $tempImageFilename, $filenames['middle'], 430);
            $imageBig = $this->resizeVariant($productDir, $tempImageFilename, $filenames['big'], 800);
        } catch (Throwable $exception) {
            $this->cleanupPaths($paths, $productDir);

            throw $exception;
        }

        $productImage = new ProductImage();
        $productImage->setFilenameSmall($imageSmall);
        $productImage->setFilenameMiddle($imageMiddle);
        $productImage->setFilenameBig($imageBig);

        return $productImage;
    }

    public function cleanupImageFiles(ProductImage $productImage, string $productImageDir): void
    {
        $filenames = [
            $productImage->getFilenameSmall(),
            $productImage->getFilenameMiddle(),
            $productImage->getFilenameBig(),
        ];
        $paths = [];
        foreach ($filenames as $filename) {
            if (is_string($filename)) {
                $paths[] = $this->filesystemWorker->generatePathToFile($productImageDir, $filename);
            }
        }

        $this->cleanupPaths($paths, $productImageDir);
    }

    public function removeImageFromProduct(ProductImage $productImage, string $productImageDir): void
    {
        $smallFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameSmall());
        $middleFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameMiddle());
        $bigFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameBig());

        /** @var Product $product */
        $product = $productImage->getProduct();
        $product->removeProductImage($productImage);

        $this->em->flush();

        $this->filesystemWorker->remove($smallFilePath);
        $this->filesystemWorker->remove($middleFilePath);
        $this->filesystemWorker->remove($bigFilePath);
        $this->filesystemWorker->removeFolderIfEmpty($productImageDir);
    }

    /** @return array{small: string, middle: string, big: string} */
    private function generateVariantFilenames(string $productDir): array
    {
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $key = bin2hex(random_bytes(16));
            $filenames = [
                'small' => $key.'_small.jpg',
                'middle' => $key.'_middle.jpg',
                'big' => $key.'_big.jpg',
            ];

            foreach ($filenames as $filename) {
                if (file_exists($this->filesystemWorker->generatePathToFile($productDir, $filename))) {
                    continue 2;
                }
            }

            return $filenames;
        }

        throw new RuntimeException('Unable to allocate image variant filenames.');
    }

    private function resizeVariant(string $productDir, string $tempImageFilename, string $filename, int $width): string
    {
        return $this->imageResizer->resizeImageAndSave($this->uploadsTempDir, $tempImageFilename, [
            'width' => $width,
            'height' => null,
            'newFolder' => $productDir,
            'newFilename' => $filename,
        ]);
    }

    /** @param list<string>|array<string, string> $paths */
    private function cleanupPaths(array $paths, string $productImageDir): void
    {
        foreach ($paths as $path) {
            try {
                $this->filesystemWorker->remove($path);
            } catch (Throwable $exception) {
                $this->logCleanupFailure('Unable to clean up a product image variant.', $exception);
            }
        }

        try {
            $this->filesystemWorker->removeFolderIfEmpty($productImageDir);
        } catch (Throwable $exception) {
            $this->logCleanupFailure('Unable to clean up an empty product image directory.', $exception);
        }
    }

    private function logCleanupFailure(string $message, Throwable $exception): void
    {
        try {
            $this->logger->warning($message, [
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging is best-effort and must not replace the primary exception.
        }
    }
}
