<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Product;
use App\Form\DTO\EditProductModel;
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
     * @param EditProductModel $editProductModel
     * @return Product
     */
    public function processEditForm(FormInterface $form, EditProductModel $editProductModel): Product
    {
        $product = new Product();

        if ($editProductModel->id) {
            $product = $this->productManager->find($editProductModel->id);
        }

        $this->productManager->persist($product);

        $product = $this->fillingProductData($product, $editProductModel);
        $newImageFile = $form->get('newImage')->getData();
        $uploadsTempDir = $this->fileSaver->getUploadsTempDir();
        $uploadsFilename = $this->fileSaver->saveUploadedFileIntoTemp($newImageFile);

        $tempImageFilename = $newImageFile
            ? $uploadsFilename
            : null;

        $this->productManager->updateProductImages($product, $tempImageFilename);

        $this->productManager->flush();

        if ($tempImageFilename) {
            $this->filesystemWorker->remove($uploadsTempDir . '/' . $uploadsFilename);
            $this->filesystemWorker->removeFolderIfEmpty($uploadsTempDir);
        }

        return $product;
    }

    /**
     * @param Product $product
     * @param EditProductModel $editProductModel
     * @return Product
     */
    private function fillingProductData(Product $product, EditProductModel $editProductModel): Product
    {
        $title = (!is_string($editProductModel->title))
            ? (string)$editProductModel->title
            : $editProductModel->title;

        $price = (!is_string($editProductModel->price))
            ? (string)$editProductModel->price
            : $editProductModel->price;

        $quantity = (!is_int($editProductModel->quantity))
            ? (int)$editProductModel->quantity
            : $editProductModel->quantity;

        $description = (!is_string($editProductModel->description))
            ? (string)$editProductModel->description
            : $editProductModel->description;

        $isPublished = (!is_bool($editProductModel->isPublished))
            ? (bool)$editProductModel->isPublished
            : $editProductModel->isPublished;

        $isDeleted = (!is_bool($editProductModel->isDeleted))
            ? (bool)$editProductModel->isDeleted
            : $editProductModel->isDeleted;

        $product->setTitle($title);
        $product->setPrice($price);
        $product->setQuantity($quantity);
        $product->setDescription($description);
        $product->setIsPublished($isPublished);
        $product->setIsDeleted($isDeleted);

        return $product;
    }
}