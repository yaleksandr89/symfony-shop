<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Product;
use App\Utils\File\FileSaver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;

class ProductFormHandler
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

    /**
     * @var FileSaver
     */
    private FileSaver $fileSaver;

    /**
     * @var string
     */
    private string $uploadsTempDir;

    /**
     * @param EntityManagerInterface $em
     * @param FileSaver $fileSaver
     * @param string $uploadsTempDir
     */
    public function __construct(EntityManagerInterface $em, FileSaver $fileSaver, string $uploadsTempDir)
    {
        $this->em = $em;
        $this->fileSaver = $fileSaver;
        $this->uploadsTempDir = $uploadsTempDir;
    }

    public function processEditForm(FormInterface $form, Product $product = null): Product
    {
        $this->em->persist($product);

        $newImageFile = $form->get('newImage')->getData();
        $tempImageFilename = $newImageFile
            ? $this->fileSaver->saveUploadedFileIntoTemp($newImageFile)
            : null;
dd($tempImageFilename);
        // TODO: ADD A NEW IMAGE WITH DIFFERENT SIZES TO THE PRODUCT
        // 1. Save product's changes (+)
        // 2. Save uploaded file into temp folder

        // 3. Work with Product (addProductImage) and ProductImage
        // 3.1 Get path folder with images of product

        // 3.2 Work with ProductImage
        // 3.2.1 Resize and save image into folder (BIG, MIDDLE, SMALL)
        // 3.2.2 Create ProductImage and return it ro Product
        // 3.3 Save Product with new ProductImage


        $this->em->flush();
        return $product;
    }
}