<?php

declare(strict_types=1);

namespace App\Tests\Unit\Bundle;

use App\AdminBundle\AdminBundle;
use App\OAuthBundle\OAuthBundle;
use App\SeoBundle\SeoBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

#[Group(name: 'unit')]
final class InternalBundlePathTest extends TestCase
{
    /** @param class-string<AbstractBundle> $bundleClass */
    #[DataProvider('internalBundles')]
    #[TestDox('Внутренний bundle использует собственный канонический каталог ресурсов')]
    public function testInternalBundleUsesItsCanonicalResourceDirectory(string $bundleClass, string $directory): void
    {
        $projectRoot = dirname(__DIR__, 3);

        self::assertSame($projectRoot.'/src/'.$directory, (new $bundleClass())->getPath());
    }

    /** @return iterable<string, array{class-string<AbstractBundle>, string}> */
    public static function internalBundles(): iterable
    {
        yield 'AdminBundle' => [AdminBundle::class, 'AdminBundle'];
        yield 'OAuthBundle' => [OAuthBundle::class, 'OAuthBundle'];
        yield 'SeoBundle' => [SeoBundle::class, 'SeoBundle'];
    }
}
