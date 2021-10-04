<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class CategoryController extends AbstractController
{
    /**
     * @Route("/category/{slug}", name="main_category_show")
     * @param Category|null $category
     * @return Response
     */
    public function show(Category $category = null): Response
    {
        if (!$category) {
            throw new NotFoundHttpException();
        }

        if ($category->getIsDeleted() === true) {
            $this->addFlash('warning', "The category {$category->getTitle()} not found!");
            return $this->redirectToRoute('main_homepage');
        }

        /** @var Product $products */
        $products = $category->getProducts()->filter(function ($element) {
            /** @var Product $product */
            $product = $element;
            return $product->getIsPublished() === true && $product->getIsDeleted() === false;
        });

        return $this->render('front/category/show.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }
}
