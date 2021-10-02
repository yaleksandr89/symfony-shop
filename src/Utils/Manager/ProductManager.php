<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

final class ProductManager
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $em;

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
    )
    {
        $this->em = $em;
        $this->productImagesDir = $productImagesDir;
        $this->productImagesManager = $productImagesManager;
    }

    /**
     * @return ObjectRepository
     */
    public function getProductRepository(): ObjectRepository
    {
        return $this->em->getRepository(Product::class);
    }

    /**
     * @param Product|null $product
     * @return void
     */
    public function persist(?Product $product): void
    {
        $this->em->persist($product);
    }

    /**
     * @return void
     */
    public function flush(): void
    {
        $this->em->flush();
    }

    public function remove()
    {
        // ...
    }

    /**
     * @param Product|null $product
     * @return string
     */
    public function getProductImagesDir(?Product $product): string
    {
        if ($product) {
            $productId = $product->getId();
        } else {
            $productId = 'ID_product_not_fount';
        }
        return sprintf('%s/%s', $this->productImagesDir, $productId);
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