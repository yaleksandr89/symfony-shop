<?php

declare(strict_types=1);

namespace App\Utils\File;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class FileSaver
{
    /**
     * @var SluggerInterface
     */
    private SluggerInterface $slugger;

    /**
     * @var string
     */
    private string $uploadsTempDir;

    public function __construct(SluggerInterface $slugger, string $uploadsTempDir)
    {
        $this->slugger = $slugger;
        $this->uploadsTempDir = $uploadsTempDir;
    }

    public function saveUploadedFileIntoTemp(UploadedFile $uploadedFile)
    {
        $originalFileName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $saveFileName = $this->slugger->slug($originalFileName);
        $filename = sprintf('%s-%s.%s', $saveFileName, uniqid('', true), $uploadedFile->guessExtension());

        $this->createFolderIfNotExist($this->uploadsTempDir);

        try {
            $uploadedFile->move($this->uploadsTempDir, $filename);
        } catch (FileException $exception) {
            return null;
        }
        return $filename;
    }

    private function createFolderIfNotExist(string $folder): void
    {
        $folderSystem = new Filesystem();

        if (!$folderSystem->exists($folder)) {
            $folderSystem->mkdir($folder);
        }
    }
}