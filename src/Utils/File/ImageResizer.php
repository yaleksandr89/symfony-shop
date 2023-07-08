<?php

declare(strict_types=1);

namespace App\Utils\File;

use App\Utils\FileSystem\FilesystemWorker;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

final class ImageResizer
{
    private Imagine $imagine;

    private FilesystemWorker $filesystemWorker;

    public function __construct(FilesystemWorker $filesystemWorker)
    {
        $this->imagine = new Imagine();
        $this->filesystemWorker = $filesystemWorker;
    }

    public function resizeImageAndSave(string $originalFileFolder, string $originalFilename, array $targetParams): string
    {
        $originalFilePath = $this->filesystemWorker->generatePathToFile($originalFileFolder, $originalFilename);
        [$imageWidth, $imageHeight] = getimagesize($originalFilePath);

        $ratio = $imageWidth / $imageHeight;
        $targetWidth = $targetParams['width'];
        $targetHeight = $targetParams['height'];

        if ($targetHeight && ($targetWidth / $targetHeight) > $ratio) {
            $targetWidth = $targetHeight * $ratio;
        } else {
            $targetHeight = $targetWidth / $ratio;
        }

        $targetFolder = $targetParams['newFolder'];
        $targetFilename = $targetParams['newFilename'];
        $targetFilePath = sprintf('%s/%s', $targetFolder, $targetFilename);

        $imagineFile = $this->imagine->open($originalFilePath);
        $imagineFile
            ->resize(new Box($targetWidth, $targetHeight))
            ->save($targetFilePath);

        return $targetFilename;
    }
}
