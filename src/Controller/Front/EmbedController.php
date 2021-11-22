<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmbedController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var UrlGeneratorInterface
     */
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @required
     * @param UrlGeneratorInterface $urlGenerator
     * @return EmbedController
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): EmbedController
    {
        $this->urlGenerator = $urlGenerator;
        return $this;
    }
    // Autowiring <<<

    public function showSimilarProducts(ProductRepository $productRepository, int $productCount = 2, int $categoryId = null): Response
    {
        $products = $productRepository->findByCategoryAndCount($categoryId, $productCount);

        return $this->render('front/_embed/_similar_products.html.twig', [
            'products' => $products,
        ]);
    }

    /**
     * @param Request $request
     * @param CategoryRepository $categoryRepository
     * @return Response
     */
    public function showHeaderMenu(Request $request, CategoryRepository $categoryRepository): Response
    {
        $preparedListCategory = [];

        /** @var Category $category */
        foreach ($categoryRepository->findActiveCategoryWithJoinProduct() as $category) {
            $preparedListCategory[] = [
                'title' => $category->getTitle(),
                'url' => $this->urlGenerator->generate('main_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->render('front/_embed/_menu/_desktop_menu.html.twig', [
            'nav_categories' => $preparedListCategory
        ]);
    }
}
