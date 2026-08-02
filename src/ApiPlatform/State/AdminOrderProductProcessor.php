<?php

declare(strict_types=1);

namespace App\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Utils\Manager\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<mixed, OrderProduct>
 */
final class AdminOrderProductProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderManager $orderManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderProduct
    {
        $this->denyUnlessVerifiedAdmin();

        if (!$data instanceof OrderProduct) {
            throw new BadRequestHttpException('Invalid order product.');
        }

        $order = $data->getAppOrder();
        $product = $data->getProduct();
        $quantity = $data->getQuantity();
        if (
            !$order instanceof Order
            || !$product instanceof Product
            || !is_int($order->getId())
            || !is_int($product->getId())
            || !$this->entityManager->contains($order)
            || !$this->entityManager->contains($product)
            || !is_int($quantity)
            || $quantity <= 0
        ) {
            throw new BadRequestHttpException('Invalid order product.');
        }

        try {
            $normalizedProductPrice = $this->orderManager->normalizeProductPrice($product);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException('Invalid product price.', $exception);
        }

        $result = $this->entityManager->wrapInTransaction(function () use (
            $data,
            $order,
            $product,
            $normalizedProductPrice
        ): OrderProduct {
            $duplicate = $this->entityManager->getRepository(OrderProduct::class)->findOneBy([
                'appOrder' => $order,
                'product' => $product,
            ]);
            if ($duplicate instanceof OrderProduct) {
                throw new ConflictHttpException('The product is already present in the order.');
            }

            $this->orderManager->addOrderProductToAggregate($data, $normalizedProductPrice);

            return $data;
        });

        return $result;
    }

    private function denyUnlessVerifiedAdmin(): void
    {
        $user = $this->security->getUser();
        if (
            !$user instanceof User
            || !$this->security->isGranted('ROLE_ADMIN')
            || !$user->isVerified()
        ) {
            throw new AccessDeniedHttpException();
        }
    }
}
