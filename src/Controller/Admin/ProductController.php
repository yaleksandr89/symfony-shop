<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\EditProductFormType;
use App\Form\Admin\FilterType\ProductFilterFormType;
use App\Form\DTO\EditProductModel;
use App\Form\Handler\ProductFormHandler;
use App\Utils\Manager\ProductManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/product', name: 'admin_product_')]
class ProductController extends BaseAdminController
{
    #[Route('/list', name: 'list')]
    public function list(Request $request, ProductFormHandler $productFormHandler): Response
    {
        $filterForm = $this->createForm(ProductFilterFormType::class, EditProductModel::makeFromProduct());
        $filterForm->handleRequest($request);

        $pagination = $productFormHandler->processOrderFiltersForm($request, $filterForm);

        return $this->render('admin/product/list.html.twig', [
            'pagination' => $pagination,
            'form' => $filterForm->createView(),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[Route('/edit', name: 'edit_blank')]
    #[Route('/add', name: 'add')]
    public function edit(Request $request, ProductFormHandler $productFormHandler, ?Product $product = null): Response
    {
        $editProductModel = EditProductModel::makeFromProduct($product);

        $form = $this->createForm(EditProductFormType::class, $editProductModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->checkTheAccessLevel()) {
                return $this->redirect($request->server->get('HTTP_REFERER'));
            }

            $product = $productFormHandler->processEditForm($form, $editProductModel);
            $this->addFlash('success', 'Your changes were saved!');

            return $this->redirectToRoute('admin_product_edit', ['id' => $product->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }

        $images = $product
            ? $product->getProductImages()->getValues()
            : [];

        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'images' => $images,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id}', name: 'delete')]
    public function delete(Request $request, Product $product, ProductManager $productManager): Response
    {
        $id = $product->getId();
        $title = $product->getTitle();

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $productManager->softRemove($product);
        $this->addFlash('warning', "[Soft delete] The product (title: $title / ID: $id) was successfully deleted!");

        return $this->redirectToRoute('admin_product_list');
    }
}
