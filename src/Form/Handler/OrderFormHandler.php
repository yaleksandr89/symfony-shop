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
    /** @var OrderManager */
    private OrderManager $orderManager;

    /** @var PaginatorInterface */
    private PaginatorInterface $paginator;

    /** @var FilterBuilderUpdater */
    private FilterBuilderUpdater $filterBuilderUpdater;

    /**
     * @param OrderManager         $orderManager
     * @param PaginatorInterface   $paginator
     * @param FilterBuilderUpdater $filterBuilderUpdater
     */
    public function __construct(OrderManager $orderManager, PaginatorInterface $paginator, FilterBuilderUpdater $filterBuilderUpdater)
    {
        $this->orderManager = $orderManager;
        $this->paginator = $paginator;
        $this->filterBuilderUpdater = $filterBuilderUpdater;
    }

    /**
     * @param EditOrderModel $editOrderModel
     *
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
     * @param Request       $request
     * @param FormInterface $filterForm
     *
     * @return PaginationInterface
     */
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

    /**
     * @param Order          $order
     * @param EditOrderModel $editCategoryModel
     *
     * @return Order
     */
    private function fillingCategoryData(Order $order, EditOrderModel $editCategoryModel): Order
    {
        $status = (!is_string($editCategoryModel->status))
            ? (int) $editCategoryModel->status
            : $editCategoryModel->status;

        $isDeleted = (!is_bool($editCategoryModel->isDeleted))
            ? (bool) $editCategoryModel->isDeleted
            : $editCategoryModel->isDeleted;

        $order->setStatus($status);
        $order->setOwner($editCategoryModel->owner);
        $order->setIsDeleted($isDeleted);
        $order->setUpdatedAt(new DateTimeImmutable());

        return $order;
    }
}
