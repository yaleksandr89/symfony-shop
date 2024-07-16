<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Form\Admin\EditOrderFormType;
use App\Form\Admin\FilterType\OrderFilterFormType;
use App\Form\DTO\EditOrderModel;
use App\Form\Handler\OrderFormHandler;
use App\Utils\Manager\OrderManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/order', name: 'admin_order_')]
class OrderController extends BaseAdminController
{
    #[Route('/list', name: 'list')]
    public function list(Request $request, OrderFormHandler $orderFormHandler): Response
    {
        $filterForm = $this->createForm(OrderFilterFormType::class, EditOrderModel::makeFromOrder());
        $filterForm->handleRequest($request);

        $pagination = $orderFormHandler->processOrderFiltersForm($request, $filterForm);

        return $this->render('admin/order/list.html.twig', [
            'pagination' => $pagination,
            'orderStatusChoice' => OrderStaticStorage::getOrderStatusChoices(),
            'form' => $filterForm->createView(),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[Route('/add', name: 'add')]
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
            $this->addFlash('success', 'Your changes were saved!');

            return $this->redirectToRoute('admin_order_edit', ['id' => $order->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }

        /** @var User $user */
        $user = $this->getUser();

        $orderProducts = [];

        return $this->render('admin/order/edit.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'orderProducts' => $orderProducts,
            'userVerified' => $user->isVerified(),
        ]);
    }

    #[Route('/delete/{id}', name: 'delete')]
    public function delete(Request $request, Order $order, OrderManager $orderManager): Response
    {
        $id = $order->getId();

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $orderManager->remove($order);
        $this->addFlash('warning', "[Soft delete] The order (ID: $id) was successfully deleted!");

        return $this->redirectToRoute('admin_order_list');
    }
}
