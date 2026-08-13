<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils\Mailer;

use App\Utils\Mailer\EmailAssetResolver;
use App\Utils\Mailer\Exception\EmailAssetUnavailableException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;

#[Group(name: 'unit')]
final class EmailAssetResolverTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/email-asset-resolver-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->projectDir.'/public/build', 0777, true));
        self::assertTrue(mkdir($this->projectDir.'/assets/images/icons', 0777, true));
        self::assertNotFalse(file_put_contents($this->projectDir.'/public/build/email.css', '.mail { color: #123; }'));
        self::assertNotFalse(file_put_contents(
            $this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png',
            'logo',
        ));
    }

    protected function tearDown(): void
    {
        $this->removePath($this->projectDir);
    }

    #[TestDox('Локальная таблица стилей возвращается с точным содержимым')]
    public function testLocalStylesheetReturnsExactContents(): void
    {
        self::assertSame('.mail { color: #123; }', $this->resolver('/build/email.css')->getStylesheet());
    }

    #[TestDox('Локальный логотип возвращается каноническим путём внутри каталога изображений')]
    public function testLocalLogoReturnsCanonicalPath(): void
    {
        $expected = realpath($this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png');

        self::assertNotFalse($expected);
        self::assertSame($expected, $this->resolver('/build/email.css')->getLogoPath());
    }

    #[TestDox('Абсолютный внешний URL таблицы стилей остаётся fatal-конфигурацией')]
    public function testAbsoluteExternalStylesheetUrlIsFatal(): void
    {
        $this->assertFatal(fn (): string => $this->resolver('https://example.test/build/email.css')->getStylesheet());
    }

    #[TestDox('Protocol-relative URL таблицы стилей остаётся fatal-конфигурацией')]
    public function testProtocolRelativeStylesheetUrlIsFatal(): void
    {
        $this->assertFatal(fn (): string => $this->resolver('//example.test/build/email.css')->getStylesheet());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidBuildPolicyUrls(): iterable
    {
        yield 'outside build root' => ['/outside/email.css'];
        yield 'wrong extension' => ['/build/email.txt'];
    }

    #[DataProvider('invalidBuildPolicyUrls')]
    #[TestDox('Нарушение build-root или CSS-extension policy остаётся fatal-конфигурацией')]
    public function testInvalidBuildPolicyIsFatal(string $url): void
    {
        $this->assertFatal(fn (): string => $this->resolver($url)->getStylesheet());
    }

    #[TestDox('Dot-dot traversal таблицы стилей остаётся fatal-конфигурацией')]
    public function testStylesheetTraversalIsFatal(): void
    {
        $this->assertFatal(fn (): string => $this->resolver('/build/../secret.css')->getStylesheet());
    }

    #[TestDox('Symlink за пределы build-root остаётся fatal и не деградирует как отсутствие файла')]
    public function testStylesheetSymlinkEscapeIsFatal(): void
    {
        self::assertNotFalse(file_put_contents($this->projectDir.'/outside.css', 'private'));
        self::assertTrue(symlink(
            $this->projectDir.'/outside.css',
            $this->projectDir.'/public/build/link.css',
        ));

        $this->assertFatal(fn (): string => $this->resolver('/build/link.css')->getStylesheet());
    }

    #[TestDox('Отсутствующая локальная таблица стилей классифицируется как недоступный декоративный ресурс')]
    public function testMissingLocalStylesheetIsUnavailable(): void
    {
        self::assertTrue(unlink($this->projectDir.'/public/build/email.css'));

        $this->expectException(EmailAssetUnavailableException::class);

        $this->resolver('/build/email.css')->getStylesheet();
    }

    #[TestDox('Отсутствующий локальный логотип классифицируется как недоступный декоративный ресурс')]
    public function testMissingLocalLogoIsUnavailable(): void
    {
        self::assertTrue(unlink($this->projectDir.'/assets/images/icons/alexander-yurchenko-php-developer.png'));

        $this->expectException(EmailAssetUnavailableException::class);

        $this->resolver('/build/email.css')->getLogoPath();
    }

    #[TestDox('Нечитаемая локальная таблица стилей классифицируется как недоступный декоративный ресурс')]
    public function testUnreadableLocalStylesheetIsUnavailable(): void
    {
        $stylesheet = $this->projectDir.'/public/build/email.css';
        self::assertTrue(chmod($stylesheet, 0000));
        self::assertFalse(is_readable($stylesheet));
        $this->expectException(EmailAssetUnavailableException::class);

        try {
            $this->resolver('/build/email.css')->getStylesheet();
        } finally {
            chmod($stylesheet, 0644);
        }
    }

    private function resolver(string $url): EmailAssetResolver
    {
        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturnCallback(
            static function (string $path) use ($url): string {
                self::assertSame('build/email.css', $path);

                return $url;
            },
        );

        return new EmailAssetResolver($packages, $this->projectDir);
    }

    /** @param callable(): string $operation */
    private function assertFatal(callable $operation): void
    {
        try {
            $operation();
            self::fail('Unsafe asset configuration must fail.');
        } catch (\RuntimeException $exception) {
            self::assertNotInstanceOf(EmailAssetUnavailableException::class, $exception);
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if (false !== $entries) {
            foreach (array_diff($entries, ['.', '..']) as $entry) {
                $this->removePath($path.DIRECTORY_SEPARATOR.$entry);
            }
        }

        rmdir($path);
    }
}
