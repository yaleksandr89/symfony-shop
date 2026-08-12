<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\File;

use App\Utils\File\FileSaver;
use App\Utils\FileSystem\FilesystemWorker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Group(name: 'unit')]
final class FileSaverTest extends TestCase
{
    #[TestDox('Отсутствие выбранного файла возвращает null и не создаёт staging-каталог')]
    public function testNoUploadReturnsNullWithoutCreatingStagingDirectory(): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/staging';

        try {
            self::assertNull($this->fileSaver($filesystem, $uploadsTempDir)->saveUploadedFileIntoTemp(null));
            self::assertDirectoryDoesNotExist($uploadsTempDir);
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Выбранные файлы сохраняются под уникальными безопасными именами внутри staging-каталога')]
    public function testSelectedUploadsAreStagedWithUniqueSafeNames(): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/staging';
        $filesystem->mkdir($root);

        try {
            $fileSaver = $this->fileSaver($filesystem, $uploadsTempDir);
            $firstFilename = $fileSaver->saveUploadedFileIntoTemp($this->uploadedPng($root.'/first.png'));
            $secondFilename = $fileSaver->saveUploadedFileIntoTemp($this->uploadedPng($root.'/second.png'));

            self::assertIsString($firstFilename);
            self::assertIsString($secondFilename);
            self::assertNotSame($firstFilename, $secondFilename);
            self::assertSame(basename($firstFilename), $firstFilename);
            self::assertStringNotContainsString('..', $firstFilename);
            self::assertFileExists($uploadsTempDir.'/'.$firstFilename);
            self::assertFileExists($uploadsTempDir.'/'.$secondFilename);
        } finally {
            $filesystem->remove($root);
        }
    }

    #[TestDox('Ошибка перемещения выбранного файла пробрасывается, а частичный staging-файл удаляется')]
    public function testMoveFailurePropagatesAndRemovesPartialStagingFile(): void
    {
        $filesystem = new Filesystem();
        $root = $this->temporaryDirectory();
        $uploadsTempDir = $root.'/staging';
        $filesystem->mkdir($root);
        $sourcePath = $root.'/source.png';
        $this->writePng($sourcePath);
        $moveFailure = new FileException('Deterministic move failure.');
        $uploadedFile = new class($sourcePath, $moveFailure) extends UploadedFile {
            public ?string $partialPath = null;

            public function __construct(string $path, private FileException $moveFailure)
            {
                parent::__construct($path, '../../catalog image.png', 'image/png', null, true);
            }

            public function move(string $directory, ?string $name = null): File
            {
                $this->partialPath = $directory.'/'.$name;
                file_put_contents($this->partialPath, 'partial');

                throw $this->moveFailure;
            }
        };

        try {
            try {
                $this->fileSaver($filesystem, $uploadsTempDir)->saveUploadedFileIntoTemp($uploadedFile);
                self::fail('A selected upload move failure must be explicit.');
            } catch (FileException $exception) {
                self::assertSame($moveFailure, $exception);
            }

            self::assertIsString($uploadedFile->partialPath);
            self::assertFileDoesNotExist($uploadedFile->partialPath);
        } finally {
            $filesystem->remove($root);
        }
    }

    private function fileSaver(Filesystem $filesystem, string $uploadsTempDir): FileSaver
    {
        return new FileSaver(new AsciiSlugger(), new FilesystemWorker($filesystem), $uploadsTempDir);
    }

    private function uploadedPng(string $path): UploadedFile
    {
        $this->writePng($path);

        return new UploadedFile($path, '../../catalog image.png', 'image/png', null, true);
    }

    private function writePng(string $path): void
    {
        $image = imagecreatetruecolor(2, 1);
        self::assertInstanceOf(\GdImage::class, $image);
        self::assertTrue(imagepng($image, $path));
    }

    private function temporaryDirectory(): string
    {
        return sys_get_temp_dir().'/file-saver-'.bin2hex(random_bytes(8));
    }
}
