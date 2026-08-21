<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\AdminBundle\DTO\EditOrderModel;
use App\AdminBundle\DTO\OrderFilterModel;
use App\AdminBundle\Form\EditOrderFormType;
use App\AdminBundle\Form\FilterType\OrderFilterFormType;
use App\AdminBundle\Handler\OrderFormHandler;
use App\Commerce\Manager\OrderManager;
use App\Commerce\Order\OrderStaticStorage;
use App\Commerce\Repository\OrderProductRepository;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends BaseAdminController
{
    public function list(
        Request $request,
        OrderFormHandler $orderFormHandler,
        OrderProductRepository $orderProductRepository,
    ): Response {
        $filterForm = $this->createForm(OrderFilterFormType::class, new OrderFilterModel());
        $filterForm->handleRequest($request);

        $pagination = $orderFormHandler->processOrderFiltersForm($request, $filterForm);
        $orderIds = [];
        foreach ($pagination->getItems() as $order) {
            $orderIds[] = (int) $order->getId();
        }

        return $this->render('@Admin/order/list.html.twig', [
            'pagination' => $pagination,
            'orderProductCounts' => $orderProductRepository->countByOrderIds($orderIds),
            'orderStatusChoice' => OrderStaticStorage::getOrderStatusChoices(),
            'form' => $filterForm->createView(),
        ]);
    }

    public function edit(Request $request, OrderFormHandler $orderFormHandler, ?Order $order = null): Response
    {
        $editOrderModel = EditOrderModel::makeFromOrder($order);

        $form = $this->createForm(EditOrderFormType::class, $editOrderModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->checkTheAccessLevel()) {
                return $this->redirect($request->server->get('HTTP_REFERER'));
            }

            $order = $orderFormHandler->processEditForm($editOrderModel);
            $this->addTranslatedFlash('success', 'flash.save_success');

            return $this->redirectToRoute('admin_order_edit', ['id' => $order->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addTranslatedFlash('warning', 'flash.form_invalid');
        }

        /** @var User $user */
        $user = $this->getUser();

        $orderProducts = [];

        return $this->render('@Admin/order/edit.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'orderProducts' => $orderProducts,
            'userVerified' => $user->isVerified(),
        ]);
    }

    public function delete(Request $request, Order $order, OrderManager $orderManager): Response
    {
        $id = $order->getId();

        if (!$this->isCsrfTokenValid('delete_order_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $orderManager->remove($order);
        $this->addTranslatedFlash('warning', 'flash.order.deleted', ['%id%' => $id]);

        return $this->redirectToRoute('admin_order_list');
    }
}
