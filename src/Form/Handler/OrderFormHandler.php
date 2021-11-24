<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Order;
use App\Form\DTO\EditOrderModel;
use App\Utils\Manager\OrderManager;
use DateTimeImmutable;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderFormHandler
{
    /**
     * @var OrderManager
     */
    private OrderManager $orderManager;
    private PaginatorInterface $paginator;

    public function __construct(OrderManager $orderManager, PaginatorInterface $paginator)
    {
        $this->orderManager = $orderManager;
        $this->paginator = $paginator;
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
     * @param Request $request
     * @param $filterForm
     * @return PaginationInterface
     */
    public function processOrderFiltersForm(Request $request, $filterForm): PaginationInterface
    {
        $query = $this->orderManager
            ->getQueryBuilder()
            ->leftJoin('o.owner', 'u')
            ->where('o.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false)
            ->getQuery();

        return $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
        );
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