<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Order;
use App\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class EditOrderModel
{
    /**
     * @var int
     */
    public $id;

    /**
     * @Assert\NotBlank(message="Please select user")
     *
     * @var User
     */
    public $owner;

    /**
     * @Assert\NotBlank(message="Please select status")
     *
     * @var int
     */
    public $status;

    /**
     * @var float
     */
    public $totalPrice;

    /**
     * @var DateTimeImmutable
     */
    public $createdAt;

    /**
     * @var bool
     */
    public $isDeleted;

    public static function makeFromOrder(Order $order = null): self
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
