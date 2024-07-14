<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Order;
use App\Entity\User;
use DateTimeImmutable;

class EditOrderModel
{
    public function __construct(
        public ?int $id = null,
        public ?User $owner = null,
        public ?int $status = null,
        public ?float $totalPrice = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?bool $isDeleted = null
    ) {
    }

    public static function makeFromOrder(?Order $order = null): self
    {
        $model = new self();

        if (!$order) {
            return $model;
        }

        $model->id = $order->getId();
        $model->owner = $order->getOwner();
        $model->status = $order->getStatus();
        $model->totalPrice = $order->getTotalPrice();
        $model->createdAt = $order->getCreatedAt();
        $model->isDeleted = $order->getIsDeleted();

        return $model;
    }
}
