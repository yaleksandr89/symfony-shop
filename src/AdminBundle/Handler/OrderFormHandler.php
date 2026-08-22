<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\AdminBundle\DTO\EditOrderModel;
use App\Commerce\Manager\OrderManager;
use App\Commerce\Repository\OrderRepository;
use App\Entity\Order;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class OrderFormHandler
{
    public function __construct(
        private OrderManager $orderManager,
        private OrderRepository $orderRepository,
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator,
        private FilterBuilderUpdater $filterBuilderUpdater,
    ) {
    }

    public function processEditForm(EditOrderModel $editOrderModel): Order
    {
        $order = new Order();

        if ($editOrderModel->id) {
            $order = $this->orderRepository->find($editOrderModel->id);
        }

        $this->orderManager->calculationOrderTotalPrice($order);

        $this->entityManager->persist($order);
        $order = $this->fillingCategoryData($order, $editOrderModel);
        $this->entityManager->flush();

        return $order;
    }

    public function processOrderFiltersForm(Request $request, FormInterface $filterForm): PaginationInterface
    {
        $queryBuilder = $this->orderRepository
            ->createQueryBuilder('o')
            ->leftJoin('o.owner', 'u')
            ->addSelect('u')
            ->where('o.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
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
