<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Order;
use App\Form\DTO\EditOrderModel;
use App\Utils\Manager\OrderManager;
use DateTimeImmutable;

class OrderFormHandler
{
    /**
     * @var OrderManager
     */
    private OrderManager $orderManager;

    public function __construct(OrderManager $orderManager)
    {
        $this->orderManager = $orderManager;
    }

    /**
     * @param EditOrderModel $editOrderModel
     * @return Order
     */
    public function processEditForm(EditOrderModel $editOrderModel): Order
    {
        $order = new Order();

        if ($editOrderModel->id) {
            $order = $this->orderManager->find($editOrderModel->id);
        }

        $this->orderManager->calculationOrderTotalPrice($order);

        $this->orderManager->persist($order);
        $order = $this->fillingCategoryData($order, $editOrderModel);
        $this->orderManager->flush();

        return $order;
    }

    /**
     * @param Order $order
     * @param EditOrderModel $editCategoryModel
     * @return Order
     */
    private function fillingCategoryData(Order $order, EditOrderModel $editCategoryModel): Order
    {
        $status = (!is_string($editCategoryModel->status))
            ? (int)$editCategoryModel->status
            : $editCategoryModel->status;

        $isDeleted = (!is_bool($editCategoryModel->isDeleted))
            ? (bool)$editCategoryModel->isDeleted
            : $editCategoryModel->isDeleted;

        $order->setStatus($status);
        $order->setOwner($editCategoryModel->owner);
        $order->setIsDeleted($isDeleted);
        $order->setUpdatedAt(new DateTimeImmutable());

        return $order;
    }
}