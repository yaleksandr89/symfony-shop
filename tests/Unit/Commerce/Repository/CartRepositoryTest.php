<?php

declare(strict_types=1);

namespace App\Tests\Unit\Commerce\Repository;

use App\Commerce\Repository\CartRepository;
use App\Entity\Cart;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
class CartRepositoryTest extends TestCase
{
    #[TestDox('PostgreSQL checkout загружает корзину с PESSIMISTIC_WRITE внутри транзакции')]
    public function testPostgreSqlCheckoutUsesPessimisticWriteInsideActiveTransaction(): void
    {
        $cartId = 42;
        $cart = new Cart();
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('isTransactionActive')
            ->willReturn(true);
        $connection->expects(self::once())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('getClassMetadata')
            ->with(Cart::class)
            ->willReturn(new ClassMetadata(Cart::class));
        $entityManager->expects(self::once())
            ->method('getConnection')
            ->willReturn($connection);
        $entityManager->expects(self::once())
            ->method('find')
            ->with(Cart::class, $cartId, LockMode::PESSIMISTIC_WRITE, null)
            ->willReturn($cart);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())
            ->method('getManagerForClass')
            ->with(Cart::class)
            ->willReturn($entityManager);

        $repository = new CartRepository($registry);

        self::assertSame($cart, $repository->findForCheckout($cartId));
    }
}
