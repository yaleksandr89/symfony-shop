<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Image;

use App\Catalog\Image\ImageResizer;
use App\Catalog\Image\FilesystemWorker;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

#[Group(name: 'unit')]
class ImageResizerTest extends TestCase
{
    #[TestDox('Некорректное изображение отклоняется до открытия Imagine')]
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
            $imageResizer->resizeImageAndSave(dirname($invalidImagePath), basename($invalidImagePath), []);
        } finally {
            if (is_file($invalidImagePath)) {
                unlink($invalidImagePath);
            }
        }
    }

    #[TestDox('Изображение вписывается в границы с сохранением пропорций и заданного имени')]
    #[DataProvider('aspectRatios')]
    public function testResizesRepresentativeAspectRatiosAndSavesRequestedTarget(
        int $sourceWidth,
        int $sourceHeight,
        int $expectedWidth,
        int $expectedHeight,
    ): void {
        $sourcePath = tempnam(sys_get_temp_dir(), 'source-image-');
        self::assertNotFalse($sourcePath);
        $targetFolder = sys_get_temp_dir();
        $targetFilename = 'resized-'.bin2hex(random_bytes(6)).'.png';
        $targetPath = $targetFolder.'/'.$targetFilename;

        try {
            $source = imagecreatetruecolor($sourceWidth, $sourceHeight);
            self::assertInstanceOf(\GdImage::class, $source);
            self::assertTrue(imagepng($source, $sourcePath));

            $image = $this->createMock(ImageInterface::class);
            $image->expects(self::once())->method('resize')->with(self::callback(
                static fn (Box $box): bool => $expectedWidth === $box->getWidth() && $expectedHeight === $box->getHeight()
            ))->willReturnSelf();
            $image->expects(self::once())->method('save')->with($targetPath)->willReturnSelf();
            $imagine = $this->createMock(Imagine::class);
            $imagine->expects(self::once())->method('open')->with($sourcePath)->willReturn($image);

            $result = (new ImageResizer($imagine, new FilesystemWorker(new Filesystem())))->resizeImageAndSave(
                dirname($sourcePath),
                basename($sourcePath),
                [
                    'width' => 100,
                    'height' => 100,
                    'newFolder' => $targetFolder,
                    'newFilename' => $targetFilename,
                ]
            );

            self::assertSame($targetFilename, $result);
        } finally {
            if (is_file($sourcePath)) {
                unlink($sourcePath);
            }
            if (is_file($targetPath)) {
                unlink($targetPath);
            }
        }
    }

    /** @return iterable<string, array{int, int, int, int}> */
    public static function aspectRatios(): iterable
    {
        yield 'landscape' => [2, 1, 100, 50];
        yield 'portrait' => [1, 2, 50, 100];
    }
}
