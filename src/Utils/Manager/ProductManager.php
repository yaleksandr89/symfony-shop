<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use RuntimeException;
use Throwable;

final class ProductManager extends AbstractBaseManager
{
    private string $productImagesDir;

    private ProductImageManager $productImagesManager;

    public function __construct(
        EntityManagerInterface $em,
        string $productImagesDir,
        ProductImageManager $productImagesManager,
    ) {
        parent::__construct($em);

        $this->productImagesDir = $productImagesDir;
        $this->productImagesManager = $productImagesManager;
    }

    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Product::class);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->getRepository()
            ->createQueryBuilder('p');
    }

    public function softRemove(object $entity): void
    {
        /** @var Product $product */
        $product = $entity;

        $this->persist($product);
        $product->setIsDeleted(true);
        $product->setIsPublished(false);
        $this->flush();
    }

    public function getProductImagesDir(Product $product): string
    {
        return sprintf('%s/%s', $this->productImagesDir, $product->getId());
    }

    public function updateProductImages(Product $product, ?string $tempImageFilename = null): Product
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

    public function saveProduct(Product $product, ?string $tempImageFilename = null): Product
    {
        if (null === $tempImageFilename) {
            $this->persist($product);
            $this->flush();

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
                if (!$productImage instanceof ProductImage) {
                    throw new RuntimeException('Unable to create product image variants.');
                }

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
