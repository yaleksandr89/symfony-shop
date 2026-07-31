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

    public function testRawOrderDeletionCascadesToItsLines(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, , $orderId] = $this->insertRawOrderProduct($connection);
            $connection->executeStatement('DELETE FROM "order" WHERE id = ?', [$orderId]);

            self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM order_product WHERE app_order_id = ?', [$orderId]));
        });
    }

    public function testRawCartDeletionCascadesToItsLines(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, $cartId] = $this->insertRawCartProduct($connection);
            $connection->executeStatement('DELETE FROM cart WHERE id = ?', [$cartId]);

            self::assertSame(0, (int) $connection->fetchOne('SELECT count(*) FROM cart_product WHERE cart_id = ?', [$cartId]));
        });
    }

    public function testProductDeletionIsRestrictedByOrderProduct(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [, $productId] = $this->insertRawOrderProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM product WHERE id = ?', [$productId]);
        });
    }

    public function testProductDeletionIsRestrictedByCartProduct(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [$productId] = $this->insertRawCartProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM product WHERE id = ?', [$productId]);
        });
    }

    public function testUserDeletionIsRestrictedByOrder(): void
    {
        $this->withForeignKeyEnforcement(function (Connection $connection): void {
            [$userId] = $this->insertRawOrderProduct($connection);

            $this->expectException(ForeignKeyConstraintViolationException::class);
            $connection->executeStatement('DELETE FROM "user" WHERE id = ?', [$userId]);
        });
    }

    public function testCommerceAssociationMetadataMatchesAggregateBoundaries(): void
    {
        $totalPrice = $this->entityManager->getClassMetadata(Order::class)->fieldMappings['totalPrice'];
        $orderProducts = $this->entityManager->getClassMetadata(Order::class)->associationMappings['orderProducts'];
        $cartProducts = $this->entityManager->getClassMetadata(Cart::class)->associationMappings['cartProducts'];
        $order = $this->entityManager->getClassMetadata(OrderProduct::class)->associationMappings['appOrder'];
        $cart = $this->entityManager->getClassMetadata(CartProduct::class)->associationMappings['cart'];
        $productCartProducts = $this->entityManager->getClassMetadata(Product::class)->associationMappings['cartProducts'];
        $product = $this->entityManager->getClassMetadata(OrderProduct::class)->associationMappings['product'];
        $owner = $this->entityManager->getClassMetadata(Order::class)->associationMappings['owner'];
        $userOrders = $this->entityManager->getClassMetadata(User::class)->associationMappings['orders'];

        self::assertSame(['persist', 'remove'], $this->mappingValue($orderProducts, 'cascade'));
        self::assertTrue($this->mappingValue($orderProducts, 'orphanRemoval'));
        self::assertSame(['persist', 'remove'], $this->mappingValue($cartProducts, 'cascade'));
        self::assertTrue($this->mappingValue($cartProducts, 'orphanRemoval'));
        self::assertSame('CASCADE', $this->mappingValue($this->mappingValue($order, 'joinColumns')[0], 'onDelete'));
        self::assertSame('CASCADE', $this->mappingValue($this->mappingValue($cart, 'joinColumns')[0], 'onDelete'));
        self::assertFalse($this->mappingValue($productCartProducts, 'orphanRemoval'));
        self::assertSame([], $this->mappingValue($productCartProducts, 'cascade'));
        self::assertNull($this->mappingValue($this->mappingValue($product, 'joinColumns')[0], 'onDelete'));
        self::assertNull($this->mappingValue($this->mappingValue($owner, 'joinColumns')[0], 'onDelete'));
        self::assertSame([], $this->mappingValue($userOrders, 'cascade'));
        self::assertSame(Types::DECIMAL, $this->mappingValue($totalPrice, 'type'));
        self::assertSame(19, $this->mappingValue($totalPrice, 'precision'));
        self::assertSame(2, $this->mappingValue($totalPrice, 'scale'));
        self::assertTrue($this->mappingValue($totalPrice, 'nullable'));
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

    private function mappingValue(array|object $mapping, string $key): mixed
    {
        if (is_array($mapping)) {
            if (!array_key_exists($key, $mapping)) {
                self::fail(sprintf('The Doctrine mapping array does not contain the "%s" key.', $key));
            }

            return $mapping[$key];
        }

        if (!property_exists($mapping, $key)) {
            self::fail(sprintf('The Doctrine mapping object %s does not have the "%s" property.', $mapping::class, $key));
        }

        return $mapping->{$key};
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
            'INSERT INTO product (uuid, title, price, quantity, created_at, is_published, is_deleted) VALUES (?, ?, 10.00, 1, CURRENT_TIMESTAMP, 0, 0)',
            ['00000000-0000-4000-8000-'.str_replace('.', '', $suffix), 'Commerce lifecycle '.$suffix],
        );

        return [$userId, (int) $connection->lastInsertId()];
    }
}
