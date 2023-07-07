<?php

declare(strict_types=1);

namespace App\Utils\Manager;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

final class OrderManager extends AbstractBaseManager
{
    private CartManager $cartManager;

    /**
     * @required
     */
    public function setCartManager(CartManager $cartManager): OrderManager
    {
        $this->cartManager = $cartManager;

        return $this;
    }

    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Order::class);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->getRepository()
            ->createQueryBuilder('o');
    }

    public function createOrderFromCartByToken(string $cartToken, User $user): void
    {
        $cart = $this->cartManager
            ->getRepository()
            ->findOneBy(['token' => $cartToken]);

        if ($cart) {
            $this->createOrderFromCart($cart, $user);
        }
    }

    public function createOrderFromCart(Cart $cart, User $user): void
    {
        $order = new Order();
        $order->setOwner($user);
        $order->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);

        $this->addOrdersProductsFromCart($order, $cart->getId());
        $this->calculationOrderTotalPrice($order);

        $this->persist($order);
        $this->flush();

        $this->cartManager->remove($cart);
    }

    public function addOrdersProductsFromCart(Order $order, int $cartId): void
    {
        /** @var Cart|null $cart */
        $cart = $this->cartManager->find($cartId);

        if ($cart) {
            /** @var CartProduct $cartProduct */
            foreach ($cart->getCartProducts()->getValues() as $cartProduct) {
                /** @var Product $product */
                $product = $cartProduct->getProduct();

                $orderProduct = new OrderProduct();
                $orderProduct->setAppOrder($order);
                $orderProduct->setQuantity($cartProduct->getQuantity());
                $orderProduct->setPricePerOne($product->getPrice());
                $orderProduct->setProduct($product);

                $order->addOrderProduct($orderProduct);
                $this->persist($orderProduct);
            }
        }
    }

    public function calculationOrderTotalPrice(Order $order): void
    {
        $orderTotalPrice = 0;

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts()->getValues() as $orderProduct) {
            $orderTotalPrice += $orderProduct->getQuantity() * $orderProduct->getPricePerOne();
        }

        $order->setTotalPrice($orderTotalPrice);
    }

    public function remove(object $entity): void
    {
        /** @var Order $order */
        $order = $entity;

        $this->em->persist($order);
        $order->setIsDeleted(true);
        $this->em->flush();
    }
}
