<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Form\Admin\EditOrderFormType;
use App\Form\DTO\EditOrderModel;
use App\Form\Handler\OrderFormHandler;
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
     * @param OrderFormHandler $orderFormHandler
     * @param Order|null $order
     * @return Response
     */
    public function edit(Request $request, OrderFormHandler $orderFormHandler, Order $order = null): Response
    {
        $editOrderModel = EditOrderModel::makeFromOrder($order);

        $form = $this->createForm(EditOrderFormType::class, $editOrderModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order = $orderFormHandler->processEditForm($editOrderModel);
            $this->addFlash('success', 'Your changes were saved!');
            return $this->redirectToRoute('admin_order_edit', ['id' => $order->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }

        $orderProducts = [];
        /** @var OrderProduct $orderProduct */
        foreach ($order->getOrderProducts()->getValues() as $orderProduct) {
            /** @var Product $product */
            $product = $orderProduct->getProduct();
            /** @var Category $category */
            $category = $product->getCategory();

            $orderProducts[] = [
                'id' => $orderProduct->getId(),
                'product' => [
                    'id' => $product->getId(),
                    'title' => $product->getTitle(),
                    'price' => $product->getPrice(),
                    'quantity' => $product->getQuantity(),
                    'category' => [
                        'id' => $category->getId(),
                        'title' => $category->getTitle(),
                    ]
                ],
                'quantity' => $orderProduct->getQuantity(),
                'pricePerOne' => $orderProduct->getPricePerOne(),
            ];
        }

        return $this->render('admin/order/edit.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
            'orderProducts' => $orderProducts,
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
