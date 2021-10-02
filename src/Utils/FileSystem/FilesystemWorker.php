<?php

declare(strict_types=1);

namespace App\Utils\FileSystem;

use FilesystemIterator;
use Symfony\Component\Filesystem\Filesystem;

final class FilesystemWorker
{
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param string $folder
     * @return void
     */
    public function createFolderIfNotExist(string $folder): void
    {
        if (!$this->filesystem->exists($folder)) {
            $this->filesystem->mkdir($folder);
        }
    }

    /**
     * @param string $item
     * @return void
     */
    public function remove(string $item): void
    {
        if ($this->filesystem->exists($item)) {
            $this->filesystem->remove($item);
        }
    }

    /**
     * @param string $pathToDir
     */
    public function removeFolderIfEmpty(string $pathToDir): void
    {
        $iterator = new FilesystemIterator($pathToDir);
        if (!$iterator->valid()) {
            $this->filesystem->remove($pathToDir);
        }
    }
}