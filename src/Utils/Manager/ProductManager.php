<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

final class ProductManager extends AbstractBaseManager
{
    /**
     * @var string
     */
    private string $productImagesDir;

    /**
     * @var ProductImageManager
     */
    private ProductImageManager $productImagesManager;

    public function __construct(
        EntityManagerInterface $em,
        string $productImagesDir,
        ProductImageManager $productImagesManager
    ) {
        parent::__construct($em);

        $this->productImagesDir = $productImagesDir;
        $this->productImagesManager = $productImagesManager;
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(Product::class);
    }

    /**
     * @param object $entity
     * @return void;
     */
    public function remove(object $entity): void
    {
        $this->persist($entity);
        $entity->setIsDeleted(true);
        $this->flush();
    }

    /**
     * @param Product $product
     * @return string
     */
    public function getProductImagesDir(Product $product): string
    {
        return sprintf('%s/%s', $this->productImagesDir, $product->getId());
    }


    public function updateProductImages(Product $product, string $tempImageFilename = null): Product
    {
        if (!$tempImageFilename) {
            return $product;
        }

        $productDir = $this->getProductImagesDir($product);

        /** @var ProductImage $productImages */
        $productImages = $this->productImagesManager->saveImageForProduct($productDir, $tempImageFilename);
        $productImages->setProduct($product);

        $product->addProductImage($productImages);

        return $product;
    }

}