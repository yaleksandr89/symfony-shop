<?php

declare(strict_types=1);

namespace App\Demo;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class DemoAssetInstaller
{
    private const VARIANTS = ['big', 'middle', 'small'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        private string $productImagesDir
    ) {
    }

    /**
     * @param array<int, array{slug: string, image_key: string}> $products
     */
    public function assertSourcesExist(array $products): void
    {
        foreach ($products as $productData) {
            foreach ($this->getSourcePaths($productData['image_key']) as $path) {
                if (!is_file($path)) {
                    throw new \RuntimeException(sprintf('Missing demo image fixture for product "%s": %s', $productData['slug'], $path));
                }
            }
        }
    }

    /**
     * @return array{created: bool, copied: int}
     */
    public function install(Product $product, string $imageKey): array
    {
        $productId = $product->getId();
        if (null === $productId) {
            throw new \RuntimeException(sprintf('Demo product "%s" must be flushed before installing images.', $product->getSlug()));
        }

        foreach ($this->getSourcePaths($imageKey) as $path) {
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Missing demo image fixture for product "%s": %s', $product->getSlug(), $path));
            }
        }

        $targetDir = sprintf('%s/%d', rtrim($this->productImagesDir, '/'), $productId);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException(sprintf('Unable to create demo product image directory: %s', $targetDir));
        }

        $copied = 0;
        foreach ($this->getSourcePaths($imageKey) as $variant => $sourcePath) {
            $targetPath = sprintf('%s/%s', $targetDir, $this->getFilename($imageKey, $variant));
            if (!copy($sourcePath, $targetPath)) {
                throw new \RuntimeException(sprintf('Unable to copy demo image for product "%s": %s -> %s', $product->getSlug(), $sourcePath, $targetPath));
            }

            ++$copied;
        }

        $created = false;
        if (!$this->hasDemoImage($product, $imageKey)) {
            $productImage = new ProductImage();
            $productImage->setFilenameBig($this->getFilename($imageKey, 'big'));
            $productImage->setFilenameMiddle($this->getFilename($imageKey, 'middle'));
            $productImage->setFilenameSmall($this->getFilename($imageKey, 'small'));
            $product->addProductImage($productImage);
            $this->entityManager->persist($productImage);
            $created = true;
        }

        return ['created' => $created, 'copied' => $copied];
    }

    private function hasDemoImage(Product $product, string $imageKey): bool
    {
        foreach ($product->getProductImages() as $productImage) {
            if (
                $productImage instanceof ProductImage
                && $productImage->getFilenameBig() === $this->getFilename($imageKey, 'big')
                && $productImage->getFilenameMiddle() === $this->getFilename($imageKey, 'middle')
                && $productImage->getFilenameSmall() === $this->getFilename($imageKey, 'small')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function getSourcePaths(string $imageKey): array
    {
        $sourceDir = sprintf('%s/fixtures/demo/images/%s', $this->kernel->getProjectDir(), $imageKey);
        $paths = [];

        foreach (self::VARIANTS as $variant) {
            $paths[$variant] = sprintf('%s/%s', $sourceDir, $this->getFilename($imageKey, $variant));
        }

        return $paths;
    }

    private function getFilename(string $imageKey, string $variant): string
    {
        return sprintf('%s_%s.jpg', $imageKey, $variant);
    }
}
