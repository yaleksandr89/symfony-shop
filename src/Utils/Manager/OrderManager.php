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
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectRepository;

final class OrderManager extends AbstractBaseManager
{
    // >>> Autowiring
    /**
     * @var CartManager
     */
    private CartManager $cartManager;

    /**
     * @required
     *
     * @param CartManager $cartManager
     *
     * @return OrderManager
     */
    public function setCartManager(CartManager $cartManager): OrderManager
    {
        $this->cartManager = $cartManager;

        return $this;
    }
    // Autowiring <<<

    /**
     * @return ObjectRepository
     */
    public function getRepository(): ObjectRepository
    {
        return $this->em->getRepository(Order::class);
    }

    /**
     * alias: 'o'.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->getRepository()
            ->createQueryBuilder('o');
    }

    /**
     * @param string $cartToken
     * @param User   $user
     *
     * @return void
     */
    public function createOrderFromCartByToken(string $cartToken, User $user): void
    {
        $cart = $this->cartManager
            ->getRepository()
            ->findOneBy(['token' => $cartToken]);

        if ($cart) {
            $this->createOrderFromCart($cart, $user);
        }
    }

    /**
     * @param Cart $cart
     * @param User $user
     *
     * @return void
     */
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

    /**
     * @param Order $order
     * @param int   $cartId
     *
     * @return void
     */
    public function addOrdersProductsFromCart(Order $order, int $cartId): void
    {
        /** @var Cart $cart */
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

    /**
     * @param Order $order
     *
     * @return void
     */
    public function calculationOrderTotalPrice(Order $order): void
    {
        $orderTotalPrice = 0;

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts()->getValues() as $orderProduct) {
            $orderTotalPrice += $orderProduct->getQuantity() * $orderProduct->getPricePerOne();
        }

        $order->setTotalPrice($orderTotalPrice);
    }

    /**
     * @param object $entity
     */
    public function remove(object $entity): void
    {
        /** @var Order $order */
        $order = $entity;

        $this->em->persist($order);
        $order->setIsDeleted(true);
        $this->em->flush();
    }
}
