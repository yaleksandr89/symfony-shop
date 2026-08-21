<?php

declare(strict_types=1);

namespace App\Catalog\Image;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

final class FileSaver
{
    public function __construct(
        private SluggerInterface $slugger,
        private FilesystemWorker $filesystemWorker,
        private string $uploadsTempDir,
    ) {
    }

    public function saveUploadedFileIntoTemp(?UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        $originalFileName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $saveFileName = $this->slugger->slug($originalFileName);
        $this->filesystemWorker->createFolderIfNotExist($this->uploadsTempDir);
        do {
            $filename = sprintf('%s-%s.%s', $saveFileName, bin2hex(random_bytes(16)), $uploadedFile->guessExtension());
            $targetPath = $this->filesystemWorker->generatePathToFile($this->uploadsTempDir, $filename);
        } while (file_exists($targetPath));

        try {
            $uploadedFile->move($this->uploadsTempDir, $filename);
        } catch (FileException $exception) {
            try {
                $this->filesystemWorker->remove($targetPath);
            } catch (Throwable) {
                // Cleanup must not replace the upload failure.
            }

            throw $exception;
        }

        return $filename;
    }

    public function getUploadsTempDir(): string
    {
        return $this->uploadsTempDir;
    }
}
