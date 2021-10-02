<?php

declare(strict_types=1);

namespace App\Utils\FileSystem;

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
}