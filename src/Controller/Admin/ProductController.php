<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\EditProductFormType;
use App\Form\Admin\FilterType\ProductFilterFormType;
use App\Form\DTO\EditProductModel;
use App\Form\DTO\ProductFilterModel;
use App\Form\Handler\ProductFormHandler;
use App\Repository\ProductImageRepository;
use App\Utils\Manager\ProductManager;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/product', name: 'admin_product_')]
class ProductController extends BaseAdminController
{
    #[Route('/list', name: 'list')]
    public function list(
        Request $request,
        ProductFormHandler $productFormHandler,
        ProductImageRepository $productImageRepository,
    ): Response {
        $filterForm = $this->createForm(ProductFilterFormType::class, new ProductFilterModel());
        $filterForm->handleRequest($request);

        $pagination = $productFormHandler->processOrderFiltersForm($request, $filterForm);
        $productIds = [];
        foreach ($pagination->getItems() as $product) {
            $productIds[] = (int) $product->getId();
        }

        return $this->render('admin/product/list.html.twig', [
            'pagination' => $pagination,
            'coversByProductId' => $productImageRepository->findFirstCoversByProductIds($productIds),
            'form' => $filterForm->createView(),
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[Route('/edit', name: 'edit_blank')]
    #[Route('/add', name: 'add')]
    public function edit(
        Request $request,
        ProductFormHandler $productFormHandler,
        TranslatorInterface $translator,
        ?Product $product = null,
    ): Response {
        $editProductModel = EditProductModel::makeFromProduct($product);

        $form = $this->createForm(EditProductFormType::class, $editProductModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->checkTheAccessLevel()) {
                return $this->redirect($request->server->get('HTTP_REFERER'));
            }

            try {
                $product = $productFormHandler->processEditForm($form, $editProductModel);
                $this->addTranslatedFlash('success', 'flash.save_success');

                return $this->redirectToRoute('admin_product_edit', ['id' => $product->getId()]);
            } catch (FileException) {
                $form->get('newImage')->addError(new FormError($translator->trans(
                    'product.validation.image.upload_failed',
                    domain: 'validators',
                )));
            }
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addTranslatedFlash('warning', 'flash.form_invalid');
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

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, ProductManager $productManager): Response
    {
        $id = $product->getId();
        $title = $product->getTitle();

        if (!$this->isCsrfTokenValid('delete_product_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $productManager->softRemove($product);
        $this->addTranslatedFlash('warning', 'flash.product.deleted', [
            '%title%' => $title,
            '%id%' => $id,
        ]);

        return $this->redirectToRoute('admin_product_list');
    }
}
