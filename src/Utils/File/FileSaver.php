<?php

declare(strict_types=1);

namespace App\Utils\File;

use App\Utils\FileSystem\FilesystemWorker;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class FileSaver
{
    public function __construct(
        private SluggerInterface $slugger,
        private FilesystemWorker $filesystemWorker,
        private string $uploadsTempDir
    ) {
    }

    public function saveUploadedFileIntoTemp(?UploadedFile $uploadedFile): ?string
    {
        if (!$uploadedFile) {
            return null;
        }

        $originalFileName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $saveFileName = $this->slugger->slug($originalFileName);
        $filename = sprintf('%s-%s.%s', $saveFileName, str_replace('.', '', uniqid('', true)), $uploadedFile->guessExtension());
        $this->filesystemWorker->createFolderIfNotExist($this->uploadsTempDir);

        try {
            $uploadedFile->move($this->uploadsTempDir, $filename);
        } catch (FileException $exception) {
            return null;
        }

        return $filename;
    }

    public function getUploadsTempDir(): string
    {
        return $this->uploadsTempDir;
    }
}
