<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\AdminBundle\DTO\EditProductModel;
use App\AdminBundle\DTO\ProductFilterModel;
use App\AdminBundle\Form\EditProductFormType;
use App\AdminBundle\Form\FilterType\ProductFilterFormType;
use App\AdminBundle\Handler\ProductFormHandler;
use App\Catalog\Manager\ProductManager;
use App\Catalog\Repository\ProductImageRepository;
use App\Entity\Product;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProductController extends BaseAdminController
{
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

        return $this->render('@Admin/product/list.html.twig', [
            'pagination' => $pagination,
            'coversByProductId' => $productImageRepository->findFirstCoversByProductIds($productIds),
            'form' => $filterForm->createView(),
        ]);
    }

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
            if ($redirect = $this->redirectIfUserIsUnverified()) {
                return $redirect;
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

        return $this->render('@Admin/product/edit.html.twig', [
            'product' => $product,
            'images' => $images,
            'form' => $form->createView(),
        ]);
    }

    public function delete(Request $request, Product $product, ProductManager $productManager): Response
    {
        $id = $product->getId();
        $title = $product->getTitle();

        if (!$this->isCsrfTokenValid('delete_product_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->redirectIfUserIsUnverified()) {
            return $redirect;
        }

        $productManager->softRemove($product);
        $this->addTranslatedFlash('warning', 'flash.product.deleted', [
            '%title%' => $title,
            '%id%' => $id,
        ]);

        return $this->redirectToRoute('admin_product_list');
    }
}
