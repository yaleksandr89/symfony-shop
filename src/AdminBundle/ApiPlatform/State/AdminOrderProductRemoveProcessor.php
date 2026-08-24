<?php

declare(strict_types=1);

namespace App\AdminBundle\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Commerce\Manager\OrderManager;
use App\Entity\Order;
use App\Entity\OrderProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
final class AdminOrderProductRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderManager $orderManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof OrderProduct) {
            throw new BadRequestHttpException('Invalid order product.');
        }

        $order = $data->getAppOrder();
        if (
            !$order instanceof Order
            || !is_int($data->getId())
            || !is_int($order->getId())
            || !$this->entityManager->contains($data)
            || !$this->entityManager->contains($order)
        ) {
            throw new BadRequestHttpException('Invalid order product.');
        }

        $this->entityManager->wrapInTransaction(function () use ($data): void {
            $this->orderManager->removeOrderProductFromAggregate($data);
        });
    }
}
