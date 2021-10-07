<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class EditOrderModel
{
    /**
     * @var int
     */
    public $id;

    /**
     * @Assert\NotBlank(message="Please select user")
     * @var User
     */
    public $owner;

    /**
     * @Assert\NotBlank(message="Please select status")
     * @var int
     */
    public $status;

    /**
     * @var bool
     */
    public $isDeleted;

    /**
     * @param Order|null $order
     * @return static
     */
    public static function makeFromOrder(?Order $order): static
    {
        $model = new self();

        if (!$order) {
            return $model;
        }

        $model->id = $order->getId();
        $model->owner = $order->getOwner();
        $model->status = $order->getStatus();
        $model->isDeleted = $order->getIsDeleted();

        return $model;
    }
}