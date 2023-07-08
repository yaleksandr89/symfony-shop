<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class ProductImageManager extends AbstractBaseManager
{
    private FilesystemWorker $filesystemWorker;

    private string $uploadsTempDir;

    private ImageResizer $imageResizer;

    public function __construct(
        EntityManagerInterface $em,
        FilesystemWorker $filesystemWorker,
        string $uploadsTempDir,
        ImageResizer $imageResizer
    ) {
        parent::__construct($em);

        $this->filesystemWorker = $filesystemWorker;
        $this->uploadsTempDir = $uploadsTempDir;
        $this->imageResizer = $imageResizer;
    }

    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(ProductImage::class);
    }

    public function saveImageForProduct(string $productDir, string $tempImageFilename = null): ?ProductImage
    {
        if (!$tempImageFilename) {
            return null;
        }

        $this->filesystemWorker->createFolderIfNotExist($productDir);

        $filenameId = str_replace('.', '', uniqid('', true));

        $imageSmallParams = [
            'width' => 60,
            'height' => null,
            'newFolder' => $productDir,
            'newFilename' => sprintf('%s_%s.jpg', $filenameId, 'small'),
        ];
        $imageSmall = $this->imageResizer->resizeImageAndSave($this->uploadsTempDir, $tempImageFilename, $imageSmallParams);

        $imageMiddleParams = [
            'width' => 430,
            'height' => null,
            'newFolder' => $productDir,
            'newFilename' => sprintf('%s_%s.jpg', $filenameId, 'middle'),
        ];
        $imageMiddle = $this->imageResizer->resizeImageAndSave($this->uploadsTempDir, $tempImageFilename, $imageMiddleParams);

        $imageBigParams = [
            'width' => 800,
            'height' => null,
            'newFolder' => $productDir,
            'newFilename' => sprintf('%s_%s.jpg', $filenameId, 'big'),
        ];
        $imageBig = $this->imageResizer->resizeImageAndSave($this->uploadsTempDir, $tempImageFilename, $imageBigParams);

        $productImage = new ProductImage();
        $productImage->setFilenameSmall($imageSmall);
        $productImage->setFilenameMiddle($imageMiddle);
        $productImage->setFilenameBig($imageBig);

        return $productImage;
    }

    public function removeImageFromProduct(ProductImage $productImage, string $productImageDir): void
    {
        $smallFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameSmall());
        $this->filesystemWorker->remove($smallFilePath);

        $middleFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameMiddle());
        $this->filesystemWorker->remove($middleFilePath);

        $bigFilePath = $this->filesystemWorker->generatePathToFile($productImageDir, $productImage->getFilenameBig());
        $this->filesystemWorker->remove($bigFilePath);

        $this->filesystemWorker->removeFolderIfEmpty($productImageDir);

        /** @var Product $product */
        $product = $productImage->getProduct();
        $product->removeProductImage($productImage);

        $this->em->flush();
    }
}
