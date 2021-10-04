<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class EmbedController extends AbstractController
{
    public function showSimilarProducts(ProductRepository $productRepository, int $productCount = 2, int $categoryId = null): Response
    {
        $products = $productRepository->findActiveProduct($productCount, $categoryId);

        return $this->render('front/_embed/_similar_products.html.twig', [
            'products' => $products,
        ]);
    }
}
