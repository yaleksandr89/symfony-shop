<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Repository\ProductRepository;
use App\Entity\Category;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends AbstractController
{
    public function show(ProductRepository $productRepository, ?Category $category = null): Response
    {
        if (!$category) {
            throw new NotFoundHttpException();
        }

        if (true === $category->getIsDeleted()) {
            $this->addFlash('warning', "The category {$category->getTitle()} not found!");

            return $this->redirectToRoute('main_homepage');
        }

        $products = $productRepository->findCardRowsByCategoryAndCount($category->getId());

        return $this->render('catalog/category/show.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
