<?php

declare(strict_types=1);

namespace App\Tests\Integration\Doctrine;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Utils\Generator\TokenGenerator;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
class CommerceAggregateLifecycleTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[TestDox('Корень заказа сохраняет и удаляет осиротевшие позиции')]
    public function testOrderRootPersistsAndOrphanRemovesItsLines(): void
    {
        [$user, $product] = $this->persistUserAndProduct();
        $order = $this->newOrder($user);
        $orderProduct = $this->newOrderProduct($product);
        $order->addOrderProduct($orderProduct);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        self::assertNotNull($orderProduct->getId());
        $orderProductId = $orderProduct->getId();
        $order->removeOrderProduct($orderProduct);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(OrderProduct::class, $orderProductId));
        self::assertNotNull($this->entityManager->find(User::class, $user->getId()));
        self::assertNotNull($this->entityManager->find(Product::class, $product->getId()));
    }

    #[TestDox('Корень корзины сохраняет и удаляет осиротевшие позиции')]
    public function testCartRootPersistsAndOrphanRemovesItsLines(): void
    {
        [, $product] = $this->persistUserAndProduct();
        $cart = (new Cart())->setToken(TokenGenerator::generateToken());
        $cartProduct = $this->newCartProduct($product);
        $cart->addCartProduct($cartProduct);

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        self::assertNotNull($cartProduct->getId());
        $cartProductId = $cartProduct->getId();
        $cart->removeCartProduct($cartProduct);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(CartProduct::class, $cartProductId));
        self::assertNotNull($this->entityManager->find(Product::class, $product->getId()));
    }

    #[TestDox('Удаление заказа удаляет его позиции, но сохраняет пользователя и товар')]
    public function testRemovingOrderRemovesItsLinesButKeepsUserAndProduct(): void
    {
        [$user, $product] = $this->persistUserAndProduct();
        $order = $this->newOrder($user);
        $order->addOrderProduct($this->newOrderProduct($product));
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $orderId = $order->getId();
        $orderProductId = $order->getOrderProducts()->first()->getId();
        $this->entityManager->remove($order);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(Order::class, $orderId));
        self::assertNull($this->entityManager->find(OrderProduct::class, $orderProductId));
        self::assertNotNull($this->entityManager->find(User::class, $user->getId()));
        self::assertNotNull($this->entityManager->find(Product::class, $product->getId()));
    }

    #[TestDox('Удаление корзины удаляет её позиции, но сохраняет товар')]
    public function testRemovingCartRemovesItsLinesButKeepsProduct(): void
    {
        [, $product] = $this->persistUserAndProduct();
        $cart = (new Cart())->setToken(TokenGenerator::generateToken());
        $cart->addCartProduct($this->newCartProduct($product));
        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        $cartId = $cart->getId();
        $cartProductId = $cart->getCartProducts()->first()->getId();
        $this->entityManager->remove($cart);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find(Cart::class, $cartId));
        self::assertNull($this->entityManager->find(CartProduct::class, $cartProductId));
        self::assertNotNull($this->entityManager->find(Product::class, $product->getId()));
    }

    #[TestDox('Прямое удаление заказа каскадно удаляет его позиции')]
    public function testRawOrderDeletionCascadesToItsLines(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, , $orderId] = $this->insertRawOrderProduct($connection);
            $connection->executeStatement('DELETE FROM "order" WHERE id = ?', [$orderId]);

            self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM order_product WHERE app_order_id = ?', [$orderId]));
        });
    }

    #[TestDox('Прямое удаление корзины каскадно удаляет её позиции')]
    public function testRawCartDeletionCascadesToItsLines(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, $cartId] = $this->insertRawCartProduct($connection);
            $connection->executeStatement('DELETE FROM cart WHERE id = ?', [$cartId]);

            self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM cart_product WHERE cart_id = ?', [$cartId]));
        });
    }

    #[TestDox('Удаление товара запрещено при наличии позиции заказа')]
    public function testProductDeletionIsRestrictedByOrderProduct(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, $productId] = $this->insertRawOrderProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM product WHERE id = ?', [$productId]);
        });
    }

    #[TestDox('Удаление товара запрещено при наличии позиции корзины')]
    public function testProductDeletionIsRestrictedByCartProduct(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [$productId] = $this->insertRawCartProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM product WHERE id = ?', [$productId]);
        });
    }

    #[TestDox('Удаление пользователя запрещено при наличии заказа')]
    public function testUserDeletionIsRestrictedByOrder(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [$userId] = $this->insertRawOrderProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM "user" WHERE id = ?', [$userId]);
        });
    }

    #[TestDox('Сумма заказа точно сохраняется, а поле объявлено как DECIMAL(19,2)')]
    public function testOrderTotalPriceRoundTripsAsExactScaledDecimal(): void
    {
        $mapping = $this->entityManager->getClassMetadata(Order::class)->getFieldMapping('totalPrice');
        self::assertSame(Types::DECIMAL, $mapping->type);
        self::assertSame(19, $mapping->precision);
        self::assertSame(2, $mapping->scale);
        self::assertTrue($mapping->nullable);

        [$user] = $this->persistUserAndProduct();
        $order = $this->newOrder($user)->setTotalPrice('900719925474.09');
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $orderId = $order->getId();
        $this->entityManager->clear();

        $reloaded = $this->entityManager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $reloaded);
        self::assertSame('900719925474.09', $reloaded->getTotalPrice());
    }

    /** @return array{User, Product} */
    private function persistUserAndProduct(): array
    {
        $suffix = uniqid('', true);
        $user = (new User())
            ->setEmail('commerce-lifecycle-'.$suffix.'@example.test')
            ->setPassword('not-used-by-this-test')
            ->setIsVerified(true);
        $product = (new Product())
            ->setTitle('Commerce lifecycle '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return [$user, $product];
    }

    private function newOrder(User $user): Order
    {
        return (new Order())
            ->setOwner($user)
            ->setStatus(1)
            ->setTotalPrice('10.00');
    }

    private function newOrderProduct(Product $product): OrderProduct
    {
        return (new OrderProduct())
            ->setProduct($product)
            ->setQuantity(1)
            ->setPricePerOne('10.00');
    }

    private function newCartProduct(Product $product): CartProduct
    {
        return (new CartProduct())
            ->setProduct($product)
            ->setQuantity(1);
    }

    private function withForeignKeyEnforcement(\Closure $assertions): void
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'path' => self::getContainer()->getParameter('kernel.project_dir').'/var/db_for_test.db',
            ],
            $configuration,
        );
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        self::assertSame(1, (int) $connection->fetchOne('PRAGMA foreign_keys'));
        $connection->beginTransaction();

        try {
            $assertions($connection);
        } finally {
            $connection->rollBack();
            $connection->close();
        }
    }

    /** @return array{int, int, int} */
    private function insertRawOrderProduct(Connection $connection): array
    {
        [$userId, $productId] = $this->insertRawUserAndProduct($connection);
        $connection->executeStatement(
            "INSERT INTO \"order\" (owner_id, created_at, updated_at, status, total_price, is_deleted) VALUES (?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, '10.00', 0)",
            [$userId],
        );
        $orderId = (int) $connection->lastInsertId();
        $connection->executeStatement(
            'INSERT INTO order_product (app_order_id, product_id, quantity, price_per_one) VALUES (?, ?, 1, 10.00)',
            [$orderId, $productId],
        );

        return [$userId, $productId, $orderId];
    }

    /** @return array{int, int} */
    private function insertRawCartProduct(Connection $connection): array
    {
        [, $productId] = $this->insertRawUserAndProduct($connection);
        $connection->executeStatement(
            'INSERT INTO cart (token, created_at) VALUES (?, CURRENT_TIMESTAMP)',
            [TokenGenerator::generateToken()],
        );
        $cartId = (int) $connection->lastInsertId();
        $connection->executeStatement(
            'INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (?, ?, 1)',
            [$cartId, $productId],
        );

        return [$productId, $cartId];
    }

    /** @return array{int, int} */
    private function insertRawUserAndProduct(Connection $connection): array
    {
        $suffix = uniqid('', true);
        $connection->executeStatement(
            'INSERT INTO "user" (email, roles, password, is_verified, is_deleted) VALUES (?, ?, ?, 1, 0)',
            ['commerce-lifecycle-'.$suffix.'@example.test', '[]', 'not-used-by-this-test'],
        );
        $userId = (int) $connection->lastInsertId();
        $connection->executeStatement(
            'INSERT INTO product (uuid, title, price, quantity, created_at, updated_at, is_published, is_deleted) VALUES (?, ?, 10.00, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0, 0)',
            ['00000000-0000-4000-8000-'.str_replace('.', '', $suffix), 'Commerce lifecycle '.$suffix],
        );

        return [$userId, (int) $connection->lastInsertId()];
    }
}
