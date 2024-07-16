<?php

declare(strict_types=1);

namespace App\Utils\FileSystem;

use FilesystemIterator;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemWorker
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    public function createFolderIfNotExist(string $folder): void
    {
        if (!$this->filesystem->exists($folder)) {
            $this->filesystem->mkdir($folder);
        }
    }

    public function remove(string $item): void
    {
        if ($this->filesystem->exists($item)) {
            $this->filesystem->remove($item);
        }
    }

    public function removeFolderIfEmpty(string $pathToDir): void
    {
        if (is_dir($pathToDir)) {
            $iterator = new FilesystemIterator($pathToDir);
            if (!$iterator->valid()) {
                $this->filesystem->remove($pathToDir);
            }
        }
    }

    public function generatePathToFile(string $dir, string $filename): string
    {
        return $dir.DIRECTORY_SEPARATOR.$filename;
    }
}
