<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Order;
use App\Utils\Manager\OrderManager;

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
     * @param Order $order
     * @return Order
     */
    public function processEditForm(Order $order): Order
    {
        dd($order);

        $this->orderManager->persist($category);
        //$category = $this->fillingCategoryData($category, $editCategoryModel);
        $this->orderManager->flush();

        return $order;
    }

    /**
     * @param Category $category
     * @param EditCategoryModel $editCategoryModel
     * @return Category
     */
    private function fillingCategoryData(Category $category, EditCategoryModel $editCategoryModel): Category
    {
        $title = (!is_string($editCategoryModel->title))
            ? (string)$editCategoryModel->title
            : $editCategoryModel->title;

        $category->setTitle($title);

        return $category;
    }
}