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
     * @param CartManager $cartManager
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
     * @param string $sessionId
     * @param User $user
     * @return void
     */
    public function createOrderFromCartBySessionId(string $sessionId, User $user): void
    {
        $cart = $this->cartManager
            ->getRepository()
            ->findOneBy(['sessionId' => $sessionId]);

        if ($cart) {
            $this->createOrderFromCart($cart, $user);
        }
    }

    /**
     * @param Cart $cart
     * @param User $user
     * @return void
     */
    public function createOrderFromCart(Cart $cart, User $user): void
    {
        $orderTotalPrice = 0;
        $order = new Order();
        $order->setOwner($user);
        $order->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);

        /** @var CartProduct $cartProduct */
        foreach ($cart->getCartProducts()->getValues() as $cartProduct) {
            /** @var Product $product */
            $product = $cartProduct->getProduct();

            $orderProduct = new OrderProduct();
            $orderProduct->setAppOrder($order);
            $orderProduct->setQuantity($cartProduct->getQuantity());
            $orderProduct->setPricePerOne($product->getPrice());
            $orderProduct->setProduct($product);

            $orderTotalPrice += $orderProduct->getQuantity() * $orderProduct->getPricePerOne();

            $order->addOrderProduct($orderProduct);

            $this->persist($orderProduct);
        }

        $order->setTotalPrice($orderTotalPrice);

        $this->persist($order);
        $this->flush();

        $this->cartManager->remove($cart);
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