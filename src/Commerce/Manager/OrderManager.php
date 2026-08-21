<?php

declare(strict_types=1);

namespace App\Commerce\Manager;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Money\DecimalMoney;
use App\Persistence\AbstractBaseManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

final class OrderManager extends AbstractBaseManager
{
    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Order::class);
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->getRepository()
            ->createQueryBuilder('o');
    }

    public function addOrdersProductsFromVerifiedCart(Order $order, Cart $cart): void
    {
        /** @var CartProduct $cartProduct */
        foreach ($cart->getCartProducts()->getValues() as $cartProduct) {
            /** @var Product $product */
            $product = $cartProduct->getProduct();
            $price = $product->getPrice();

            if (null === $price) {
                throw new \InvalidArgumentException('Product price must be set.');
            }

            $orderProduct = new OrderProduct();
            $orderProduct->setAppOrder($order);
            $orderProduct->setQuantity($cartProduct->getQuantity());
            $orderProduct->setPricePerOne(DecimalMoney::fromCents(DecimalMoney::toCents($price)));
            $orderProduct->setProduct($product);

            $order->addOrderProduct($orderProduct);
            $this->persist($orderProduct);
        }
    }

    public function calculationOrderTotalPrice(Order $order): void
    {
        $orderTotalCents = 0;

        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts()->getValues() as $orderProduct) {
            $quantity = $orderProduct->getQuantity();
            $pricePerOne = $orderProduct->getPricePerOne();

            if (null === $quantity || null === $pricePerOne) {
                throw new \InvalidArgumentException('Order product price and quantity must be set.');
            }

            $orderTotalCents = DecimalMoney::addCents(
                $orderTotalCents,
                DecimalMoney::multiplyToCents($pricePerOne, $quantity)
            );
        }

        $order->setTotalPrice(DecimalMoney::fromCents($orderTotalCents));
    }

    public function normalizeProductPrice(Product $product): string
    {
        $price = $product->getPrice();
        if (null === $price) {
            throw new \InvalidArgumentException('Product price must be set.');
        }

        return DecimalMoney::normalize($price);
    }

    public function addOrderProductToAggregate(OrderProduct $orderProduct, string $normalizedProductPrice): void
    {
        $order = $orderProduct->getAppOrder();
        if (!$order instanceof Order) {
            throw new \InvalidArgumentException('Order product order must be set.');
        }

        $orderProduct->setPricePerOne($normalizedProductPrice);
        $order->addOrderProduct($orderProduct);
        $this->em->persist($orderProduct);
        $this->calculationOrderTotalPrice($order);
    }

    public function removeOrderProductFromAggregate(OrderProduct $orderProduct): void
    {
        $order = $orderProduct->getAppOrder();
        if (!$order instanceof Order) {
            throw new \InvalidArgumentException('Order product order must be set.');
        }

        $order->removeOrderProduct($orderProduct);
        $this->em->remove($orderProduct);
        $this->calculationOrderTotalPrice($order);
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
