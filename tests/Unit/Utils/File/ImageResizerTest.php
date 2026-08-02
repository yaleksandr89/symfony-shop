<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\File;

use App\Utils\File\ImageResizer;
use App\Utils\FileSystem\FilesystemWorker;
use Imagine\Gd\Imagine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[Group(name: 'unit')]
class ImageResizerTest extends TestCase
{
    public function testInvalidImageIsRejectedBeforeImagineIsOpened(): void
    {
        $invalidImagePath = tempnam(sys_get_temp_dir(), 'invalid-image-');
        self::assertNotFalse($invalidImagePath);

        try {
            self::assertSame(17, file_put_contents($invalidImagePath, 'not an image file'));

            $imagine = $this->createMock(Imagine::class);
            $imagine->expects(self::never())->method('open');
            $imageResizer = new ImageResizer($imagine, new FilesystemWorker(new Filesystem()));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(sprintf('Unable to determine image size for "%s".', $invalidImagePath));

            $imageResizer->resizeImageAndSave(dirname($invalidImagePath), basename($invalidImagePath), []);
        } finally {
            if (is_file($invalidImagePath)) {
                unlink($invalidImagePath);
            }
        }
    }
}
