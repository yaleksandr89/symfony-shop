<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Category;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    /**
     * @Route("/category/{slug}", name="main_category_show")
     *
     * @param ProductRepository $productRepository
     * @param Category|null     $category
     *
     * @return Response
     */
    public function show(ProductRepository $productRepository, Category $category = null): Response
    {
        if (!$category) {
            throw new NotFoundHttpException();
        }

        if (true === $category->getIsDeleted()) {
            $this->addFlash('warning', "The category {$category->getTitle()} not found!");

            return $this->redirectToRoute('main_homepage');
        }

        $products = $productRepository->findByCategoryAndCount($category->getId());

        return $this->render('front/category/show.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
