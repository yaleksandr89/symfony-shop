<?php

declare(strict_types=1);

namespace App\Controller\AdminZone;

use App\Entity\Product;
use App\Form\EditProductFormType;
use App\Form\Handler\ProductFormHandler;
use App\Repository\ProductRepository;
use App\Utils\Manager\ProductManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/product", name="admin_product_")
 */
class ProductController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;

    /**
     * @required
     * @param ProductRepository $productRepository
     * @return ProductController
     */
    public function setProductRepository(ProductRepository $productRepository): ProductController
    {
        $this->productRepository = $productRepository;
        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/list", name="list")
     */
    public function list(ProductRepository $productRepository): Response
    {
        //$products = $this->productRepository->findAll();
        $products = $this->productRepository->findBy(['isDeleted' => false], ['id' => 'DESC'], 50);

        return $this->render('admin/product/list.html.twig', [
            'products' => $products
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @Route("/add", name="add")
     * @param Request $request
     * @param ProductFormHandler $productFormHandler
     * @param Product|null $product
     * @return Response
     */
    public function edit(Request $request, ProductFormHandler $productFormHandler, Product $product = null): Response
    {
        if (!$product) {
            $product = new Product();
        }

        $form = $this->createForm(EditProductFormType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product = $productFormHandler->processEditForm($form, $product);
            return $this->redirectToRoute('admin_product_edit', ['id' => $product->getId()]);
        }

        $images = $product->getProductImages()
            ? $product->getProductImages()->getValues()
            : [];

        return $this->render('admin/product/edit.html.twig', [
            'product' => $product,
            'images' => $images,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Product $product
     * @param ProductManager $productManager
     * @return Response
     */
    public function delete(Product $product, ProductManager $productManager): Response
    {
        $productManager->remove($product);

        return $this->redirectToRoute('admin_product_list');
    }
}
