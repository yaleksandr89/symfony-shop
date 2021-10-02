<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Product;
use App\Utils\File\FileSaver;
use App\Utils\FileSystem\FilesystemWorker;
use App\Utils\Manager\ProductManager;
use Symfony\Component\Form\FormInterface;

class ProductFormHandler
{
    /**
     * @var FileSaver
     */
    private FileSaver $fileSaver;

    /**
     * @var ProductManager
     */
    private ProductManager $productManager;

    /**
     * @var FilesystemWorker
     */
    private FilesystemWorker $filesystemWorker;

    /**
     * @param ProductManager $productManager
     * @param FileSaver $fileSaver
     * @param FilesystemWorker $filesystemWorker
     */
    public function __construct(
        ProductManager $productManager,
        FileSaver $fileSaver,
        FilesystemWorker $filesystemWorker
    ) {
        $this->fileSaver = $fileSaver;
        $this->productManager = $productManager;
        $this->filesystemWorker = $filesystemWorker;
    }

    /**
     * @param FormInterface $form
     * @param Product|null $product
     * @return Product
     */
    public function processEditForm(FormInterface $form, ?Product $product): Product
    {
        $newImageFile = $form->get('newImage')->getData();

        $uploadsTempDir = $this->fileSaver->getUploadsTempDir();
        $uploadsFilename = $this->fileSaver->saveUploadedFileIntoTemp($newImageFile);

        $tempImageFilename = $newImageFile
            ? $uploadsFilename
            : null;

        $this->productManager->updateProductImages($product, $tempImageFilename);

        $this->productManager->persist($product);
        $this->productManager->flush();

        if ($tempImageFilename) {
            $this->filesystemWorker->remove($uploadsTempDir . '/' . $uploadsFilename);
            $this->filesystemWorker->removeFolderIfEmpty($uploadsTempDir);
        }

        return $product;
    }
}