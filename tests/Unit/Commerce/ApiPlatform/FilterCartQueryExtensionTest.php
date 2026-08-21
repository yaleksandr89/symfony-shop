<?php

declare(strict_types=1);

namespace App\Tests\Unit\Commerce\ApiPlatform;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Commerce\ApiPlatform\Extension\FilterCartQueryExtension;
use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

#[Group(name: 'unit')]
class FilterCartQueryExtensionTest extends TestCase
{
    #[TestDox('Коллекция корзины использует привязку параметров')]
    public function testCollectionCartUsesParameterBinding(): void
    {
        $token = "opaque-token-'quoted";
        $queryBuilder = $this->queryBuilder('cart');
        $queryNameGenerator = $this->queryNameGenerator('cart_token_p1');

        $this->extension(Request::create('/api/carts', 'GET', [], ['CART_TOKEN' => $token]))
            ->applyToCollection($queryBuilder, $queryNameGenerator, Cart::class);

        self::assertStringContainsString('cart.token = :cart_token_p1', $queryBuilder->getDQL());
        self::assertStringNotContainsString($token, $queryBuilder->getDQL());
        self::assertSame($token, $queryBuilder->getParameter('cart_token_p1')?->getValue());
    }

    #[TestDox('Элемент корзины использует привязку параметров')]
    public function testItemCartUsesParameterBinding(): void
    {
        $token = "opaque-token-'quoted";
        $queryBuilder = $this->queryBuilder('cart');
        $queryNameGenerator = $this->queryNameGenerator('cart_token_p1');

        $this->extension(Request::create('/api/carts/1', 'GET', [], ['CART_TOKEN' => $token]))
            ->applyToItem($queryBuilder, $queryNameGenerator, Cart::class, ['id' => 1]);

        self::assertStringContainsString('cart.token = :cart_token_p1', $queryBuilder->getDQL());
        self::assertStringNotContainsString($token, $queryBuilder->getDQL());
        self::assertSame($token, $queryBuilder->getParameter('cart_token_p1')?->getValue());
    }

    #[TestDox('Другие ресурсы не изменяются')]
    public function testOtherResourceIsUntouched(): void
    {
        $queryBuilder = $this->queryBuilder('product');
        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->expects(self::never())->method('generateParameterName');

        $this->extension(Request::create('/api/products'))
            ->applyToCollection($queryBuilder, $queryNameGenerator, Product::class);

        self::assertStringNotContainsString('WHERE', $queryBuilder->getDQL());
        self::assertCount(0, $queryBuilder->getParameters());
    }

    #[TestDox('Без cookie передаётся пустой параметр')]
    public function testMissingCookieUsesAnEmptyParameter(): void
    {
        $queryBuilder = $this->queryBuilder('cart');

        $this->extension(Request::create('/api/carts'))
            ->applyToCollection($queryBuilder, $this->queryNameGenerator('cart_token_p1'), Cart::class);

        self::assertStringContainsString('cart.token = :cart_token_p1', $queryBuilder->getDQL());
        self::assertSame('', $queryBuilder->getParameter('cart_token_p1')?->getValue());
    }

    #[TestDox('Без текущего запроса передаётся пустой параметр')]
    public function testMissingCurrentRequestUsesAnEmptyParameter(): void
    {
        $queryBuilder = $this->queryBuilder('cart');

        $this->extension()
            ->applyToCollection($queryBuilder, $this->queryNameGenerator('cart_token_p1'), Cart::class);

        self::assertStringContainsString('cart.token = :cart_token_p1', $queryBuilder->getDQL());
        self::assertSame('', $queryBuilder->getParameter('cart_token_p1')?->getValue());
    }

    #[TestDox('Административный контекст обходит фильтр')]
    public function testAdminContextBypassesFilter(): void
    {
        $queryBuilder = $this->queryBuilder('cart');
        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->expects(self::never())->method('generateParameterName');
        $admin = (new User())->setRoles(['ROLE_ADMIN']);

        $this->extension(Request::create('/api/carts?context=admin', 'GET'), $admin)
            ->applyToCollection($queryBuilder, $queryNameGenerator, Cart::class);

        self::assertStringNotContainsString('WHERE', $queryBuilder->getDQL());
        self::assertCount(0, $queryBuilder->getParameters());
    }

    #[TestDox('Не-администратор не обходит фильтр')]
    public function testNonAdminsCannotBypassFilter(): void
    {
        foreach ([null, (new User())->setRoles(['ROLE_USER'])] as $user) {
            $queryBuilder = $this->queryBuilder('cart');

            $this->extension(Request::create('/api/carts?context=admin', 'GET', [], ['CART_TOKEN' => 'opaque-token']), $user)
                ->applyToCollection($queryBuilder, $this->queryNameGenerator('cart_token_p1'), Cart::class);

            self::assertStringContainsString('cart.token = :cart_token_p1', $queryBuilder->getDQL());
            self::assertSame('opaque-token', $queryBuilder->getParameter('cart_token_p1')?->getValue());
        }
    }

    private function extension(?Request $request = null, ?UserInterface $user = null): FilterCartQueryExtension
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $requestStack = new RequestStack();
        if (null !== $request) {
            $requestStack->push($request);
        }

        return (new FilterCartQueryExtension())
            ->setSecurity($security)
            ->setRequest($requestStack);
    }

    private function queryBuilder(string $alias): QueryBuilder
    {
        return (new QueryBuilder($this->createStub(EntityManagerInterface::class)))
            ->select($alias)
            ->from(Cart::class, $alias);
    }

    private function queryNameGenerator(string $parameterName): QueryNameGeneratorInterface
    {
        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator
            ->expects(self::once())
            ->method('generateParameterName')
            ->with('cart_token')
            ->willReturn($parameterName);

        return $queryNameGenerator;
    }
}
