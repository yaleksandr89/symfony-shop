<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Product;
use App\Utils\File\FileSaver;
use App\Utils\Manager\ProductManager;
use Doctrine\ORM\EntityManagerInterface;
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
     * @param ProductManager $productManager
     * @param FileSaver $fileSaver
     */
    public function __construct(ProductManager $productManager, FileSaver $fileSaver)
    {
        $this->fileSaver = $fileSaver;
        $this->productManager = $productManager;
    }

    /**
     * @param FormInterface $form
     * @param Product|null $product
     * @return Product
     */
    public function processEditForm(FormInterface $form, Product $product = null): Product
    {
        $this->productManager->persist($product);

        $newImageFile = $form->get('newImage')->getData();

        $tempImageFilename = $newImageFile
            ? $this->fileSaver->saveUploadedFileIntoTemp($newImageFile)
            : null;

        $this->productManager->updateProductImages($product, $tempImageFilename);

        // TODO: ADD A NEW IMAGE WITH DIFFERENT SIZES TO THE PRODUCT
        // 1. Save product's changes (+)
        // 2. Save uploaded file into temp folder (+)

        // 3. Work with Product (addProductImage) and ProductImage
        // 3.1 Get path folder with images of product (+)

        // 3.2 Work with ProductImage
        // 3.2.1 Resize and save image into folder (BIG, MIDDLE, SMALL) (+)
        // 3.2.2 Create ProductImage and return it ro Product (+)
        // 3.3 Save Product with new ProductImage (+)

        $this->productManager->flush();
        dd($product);
        return $product;
    }
}