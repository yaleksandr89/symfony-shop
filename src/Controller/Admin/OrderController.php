<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Repository\OrderRepository;
use App\Utils\Manager\OrderManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/order", name="admin_order_")
 */
class OrderController extends AbstractController
{
    // >>> Autowiring
    // Autowiring <<<

    /**
     * @Route("/list", name="list")
     * @param OrderRepository $orderRepository
     * @return Response
     */
    public function list(OrderRepository $orderRepository): Response
    {
        /** @var Order $orders */
        $orders = $orderRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 50);

        return $this->render('admin/order/list.html.twig', [
            'orders' => $orders,
            'orderStatusChoice' => OrderStaticStorage::getOrderStatusChoices()
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @Route("/add", name="add")
     * @param Request $request
     * @param Order|null $order
     * @return Response
     */
    public function edit(Request $request, Order $order = null): Response
    {
        /*$editCategoryModel = EditCategoryModel::makeFromCategory($category);

        $form = $this->createForm(EditCategoryFormType::class, $editCategoryModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $categoryFormHandler->processEditForm($editCategoryModel);
            $this->addFlash('success', 'Your changes were saved!');
            return $this->redirectToRoute('admin_category_list', ['id' => $category->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }*/

        return $this->render('admin/order/edit.html.twig', [
            'category' => '$category',
            'form' => '$form->createView()',
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Order $order
     * @param OrderManager $orderManager
     * @return Response
     */
    public function delete(Order $order, OrderManager $orderManager): Response
    {
        $id = $order->getId();

        $orderManager->remove($order);
        $this->addFlash('warning', "[Soft delete] The order (ID: $id) was successfully deleted!");

        return $this->redirectToRoute('admin_order_list');
    }
}
