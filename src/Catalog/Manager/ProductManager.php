<?php

declare(strict_types=1);

namespace App\Catalog\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Throwable;

final class ProductManager
{
    private string $productImagesDir;

    private ProductImageManager $productImagesManager;

    public function __construct(
        private EntityManagerInterface $em,
        string $productImagesDir,
        ProductImageManager $productImagesManager,
    ) {
        $this->productImagesDir = $productImagesDir;
        $this->productImagesManager = $productImagesManager;
    }

    public function softRemove(Product $product): void
    {
        $this->em->persist($product);
        $product->setIsDeleted(true);
        $product->setIsPublished(false);
        $this->em->flush();
    }

    public function getProductImagesDir(Product $product): string
    {
        return sprintf('%s/%s', $this->productImagesDir, $product->getId());
    }

    public function saveProduct(Product $product, ?string $tempImageFilename = null): Product
    {
        if (null === $tempImageFilename) {
            $this->em->persist($product);
            $this->em->flush();

            return $product;
        }

        $productImage = null;
        $productDir = null;

        try {
            return $this->em->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
                $product,
                $tempImageFilename,
                &$productImage,
                &$productDir,
            ): Product {
                $entityManager->persist($product);
                if (null === $product->getId()) {
                    $entityManager->flush();
                }

                if (null === $product->getId()) {
                    throw new RuntimeException('Unable to obtain a numeric product identifier.');
                }

                $productDir = $this->getProductImagesDir($product);
                $productImage = $this->productImagesManager->saveImageForProduct($productDir, $tempImageFilename);
                $product->addProductImage($productImage);

                return $product;
            });
        } catch (Throwable $exception) {
            if ($productImage instanceof ProductImage && is_string($productDir)) {
                $this->productImagesManager->cleanupImageFiles($productImage, $productDir);
            }

            throw $exception;
        }
    }
}
