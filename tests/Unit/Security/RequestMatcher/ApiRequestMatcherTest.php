<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\RequestMatcher;

use App\Security\RequestMatcher\ApiRequestMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group(name: 'unit')]
final class ApiRequestMatcherTest extends TestCase
{
    #[DataProvider('paths')]
    #[TestDox('API matcher соблюдает точную границу пути')]
    public function testMatchesOnlyApiPathBoundary(string $path, bool $expected): void
    {
        self::assertSame($expected, (new ApiRequestMatcher())->matches(Request::create($path)));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function paths(): iterable
    {
        yield 'API root' => ['/api', true];
        yield 'API root with slash' => ['/api/', true];
        yield 'API operation' => ['/api/orders', true];
        yield 'similar apiary prefix' => ['/apiary', false];
        yield 'similar apis prefix' => ['/apis', false];
        yield 'localized browser path' => ['/ru/api/cart', false];
    }
}
