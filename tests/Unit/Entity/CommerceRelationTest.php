<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group(name: 'unit')]
class CommerceRelationTest extends TestCase
{
    #[TestDox('Коллекции сущностей инициализированы')]
    public function testCollectionsAreInitialized(): void
    {
        self::assertCount(0, (new Order())->getOrderProducts());
        self::assertCount(0, (new Cart())->getCartProducts());
        self::assertCount(0, (new Product())->getCartProducts());
        self::assertCount(0, (new Product())->getOrderProducts());
        self::assertCount(0, (new User())->getOrders());
    }

    #[TestDox('Корень заказа синхронизирует и удаляет позицию без обнуления владельца')]
    public function testOrderRootSynchronizesAndRemovesOrderProductWithoutNullingOwner(): void
    {
        $order = new Order();
        $orderProduct = new OrderProduct();

        $order->addOrderProduct($orderProduct);
        $order->addOrderProduct($orderProduct);

        self::assertCount(1, $order->getOrderProducts());
        self::assertSame($order, $orderProduct->getAppOrder());

        $order->removeOrderProduct($orderProduct);

        self::assertCount(0, $order->getOrderProducts());
        self::assertSame($order, $orderProduct->getAppOrder());
    }

    #[TestDox('Корень корзины синхронизирует и удаляет позицию без обнуления владельца')]
    public function testCartRootSynchronizesAndRemovesCartProductWithoutNullingOwner(): void
    {
        $cart = new Cart();
        $cartProduct = new CartProduct();

        $cart->addCartProduct($cartProduct);
        $cart->addCartProduct($cartProduct);

        self::assertCount(1, $cart->getCartProducts());
        self::assertSame($cart, $cartProduct->getCart());

        $cart->removeCartProduct($cartProduct);

        self::assertCount(0, $cart->getCartProducts());
        self::assertSame($cart, $cartProduct->getCart());
    }
}
