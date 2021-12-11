<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    /**
     * @Route("/product/{uuid}", name="main_product_show")
     * @Route("/product", name="main_product_show_blank")
     *
     * @param Product|null $product
     *
     * @return Response
     */
    public function show(Product $product = null): Response
    {
        if (!$product) {
            throw new NotFoundHttpException();
        }

        if (true === $product->getIsDeleted()) {
            $this->addFlash('warning', "The product {$product->getTitle()} not found!");

            return $this->redirectToRoute('main_homepage');
        }

        return $this->render('front/product/show.html.twig', [
            'product' => $product,
        ]);
    }
}
