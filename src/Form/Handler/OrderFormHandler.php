<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Order;
use App\Form\DTO\EditOrderModel;
use App\Utils\Manager\OrderManager;
use DateTimeImmutable;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderFormHandler
{
    public function __construct(
        private OrderManager $orderManager,
        private PaginatorInterface $paginator,
        private FilterBuilderUpdater $filterBuilderUpdater
    ) {
    }

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

    public function processOrderFiltersForm(Request $request, FormInterface $filterForm): PaginationInterface
    {
        $queryBuilder = $this->orderManager
            ->getQueryBuilder()
            ->leftJoin('o.owner', 'u')
            ->where('o.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false);

        if ($filterForm->isSubmitted()) {
            $this->filterBuilderUpdater->addFilterConditions($filterForm, $queryBuilder);
        }

        return $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
        );
    }

    private function fillingCategoryData(Order $order, EditOrderModel $editCategoryModel): Order
    {
        $order->setStatus($editCategoryModel->status);
        $order->setOwner($editCategoryModel->owner);
        $order->setIsDeleted($editCategoryModel->isDeleted);
        $order->setUpdatedAt(new DateTimeImmutable());

        return $order;
    }
}
