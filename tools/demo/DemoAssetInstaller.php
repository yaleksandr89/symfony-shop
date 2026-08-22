<?php

declare(strict_types=1);

namespace Tools\Demo;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

final class DemoAssetInstaller
{
    private const VARIANTS = ['big', 'middle', 'small'];

    /** @var list<array{target: string, backup: string|null}> */
    private array $fileJournal = [];

    /** @var list<string> */
    private array $createdDirectories = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KernelInterface $kernel,
        private string $productImagesDir,
    ) {
    }

    /** @param array<int, array<string, mixed>> $products */
    public function assertSourcesExist(array $products): void
    {
        foreach ($products as $product) {
            foreach ($this->getSourcePaths((string) $product['image_key']) as $path) {
                if (!is_file($path)) {
                    throw new \RuntimeException(sprintf('Missing demo image fixture for product "%s": %s', $product['slug'], $path));
                }
            }
        }
    }

    /**
     * @return array{record: 'created'|'updated'|'existing', files: array{copied: int, updated: int, existing: int}}
     */
    public function install(Product $product, string $imageKey): array
    {
        $productId = $product->getId();
        if (null === $productId) {
            throw new \RuntimeException(sprintf('Demo product "%s" must be flushed before installing images.', $product->getSlug()));
        }

        $sourcePaths = $this->getSourcePaths($imageKey);
        foreach ($sourcePaths as $path) {
            if (!is_file($path)) {
                throw new \RuntimeException(sprintf('Missing demo image fixture for product "%s": %s', $product->getSlug(), $path));
            }
        }

        $targetDirectory = sprintf('%s/%d', rtrim($this->productImagesDir, '/'), $productId);
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
                throw new \RuntimeException(sprintf('Unable to create demo product image directory: %s', $targetDirectory));
            }
            $this->createdDirectories[] = $targetDirectory;
        }

        $fileCounts = ['copied' => 0, 'updated' => 0, 'existing' => 0];
        foreach ($sourcePaths as $variant => $sourcePath) {
            $targetPath = sprintf('%s/%s', $targetDirectory, $this->getFilename($imageKey, $variant));
            if (!file_exists($targetPath)) {
                $this->copyWithJournal($sourcePath, $targetPath, null);
                ++$fileCounts['copied'];
            } elseif ($this->filesAreIdentical($sourcePath, $targetPath)) {
                ++$fileCounts['existing'];
            } else {
                $backup = tempnam(sys_get_temp_dir(), 'demo-image-backup-');
                if (false === $backup || !copy($targetPath, $backup)) {
                    throw new \RuntimeException(sprintf('Unable to back up demo product image: %s', $targetPath));
                }
                $this->copyWithJournal($sourcePath, $targetPath, $backup);
                ++$fileCounts['updated'];
            }
        }

        return ['record' => $this->reconcileImageRecord($product, $imageKey), 'files' => $fileCounts];
    }

    public function commit(): void
    {
        foreach ($this->fileJournal as $change) {
            if (null !== $change['backup'] && is_file($change['backup'])) {
                unlink($change['backup']);
            }
        }
        $this->fileJournal = [];
        $this->createdDirectories = [];
    }

    public function rollback(): void
    {
        foreach (array_reverse($this->fileJournal) as $change) {
            if (null === $change['backup']) {
                if (is_file($change['target'])) {
                    unlink($change['target']);
                }
                continue;
            }
            if (is_file($change['backup'])) {
                copy($change['backup'], $change['target']);
                unlink($change['backup']);
            }
        }
        foreach (array_reverse($this->createdDirectories) as $directory) {
            if (is_dir($directory) && !(new \FilesystemIterator($directory))->valid()) {
                rmdir($directory);
            }
        }
        $this->fileJournal = [];
        $this->createdDirectories = [];
    }

    /** @return 'created'|'updated'|'existing' */
    private function reconcileImageRecord(Product $product, string $imageKey): string
    {
        $expected = [
            'big' => $this->getFilename($imageKey, 'big'),
            'middle' => $this->getFilename($imageKey, 'middle'),
            'small' => $this->getFilename($imageKey, 'small'),
        ];
        $matchingImage = null;
        $conflictingImages = [];
        foreach ($product->getProductImages() as $image) {
            if (!$image instanceof ProductImage) {
                continue;
            }
            if ($image->getFilenameBig() === $expected['big'] && $image->getFilenameMiddle() === $expected['middle'] && $image->getFilenameSmall() === $expected['small']) {
                $matchingImage ??= $image;
                if ($matchingImage !== $image) {
                    $conflictingImages[] = $image;
                }
            } else {
                $conflictingImages[] = $image;
            }
        }

        $recordState = null === $matchingImage ? (count($conflictingImages) > 0 ? 'updated' : 'created') : (count($conflictingImages) > 0 ? 'updated' : 'existing');
        if (null === $matchingImage) {
            $matchingImage = (new ProductImage())
                ->setFilenameBig($expected['big'])
                ->setFilenameMiddle($expected['middle'])
                ->setFilenameSmall($expected['small']);
            $product->addProductImage($matchingImage);
            $this->entityManager->persist($matchingImage);
        }
        foreach ($conflictingImages as $image) {
            $product->removeProductImage($image);
            $this->entityManager->remove($image);
        }

        return $recordState;
    }

    private function copyWithJournal(string $source, string $target, ?string $backup): void
    {
        $this->fileJournal[] = ['target' => $target, 'backup' => $backup];
        if (!copy($source, $target)) {
            throw new \RuntimeException(sprintf('Unable to install demo image: %s -> %s', $source, $target));
        }
    }

    private function filesAreIdentical(string $source, string $target): bool
    {
        return filesize($source) === filesize($target)
            && hash_equals((string) hash_file('sha256', $source), (string) hash_file('sha256', $target));
    }

    /** @return array<string, string> */
    private function getSourcePaths(string $imageKey): array
    {
        $sourceDirectory = sprintf('%s/fixtures/demo/images/%s', $this->kernel->getProjectDir(), $imageKey);
        $paths = [];
        foreach (self::VARIANTS as $variant) {
            $paths[$variant] = sprintf('%s/%s', $sourceDirectory, $this->getFilename($imageKey, $variant));
        }

        return $paths;
    }

    private function getFilename(string $imageKey, string $variant): string
    {
        return sprintf('%s_%s.jpg', $imageKey, $variant);
    }
}
