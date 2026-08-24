<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;

class EmbedController extends AbstractController
{
    private UrlGeneratorInterface $urlGenerator;

    #[Required]
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): EmbedController
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    public function showSimilarProducts(ProductRepository $productRepository, int $productCount = 2, ?int $categoryId = null): Response
    {
        $products = $productRepository->findCardRowsByCategoryAndCount($categoryId, $productCount);

        return $this->render('catalog/product/_similar_products.html.twig', [
            'products' => $products,
        ]);
    }

    public function showHeaderMenu(CategoryRepository $categoryRepository, ?string $isActiveItemMenu): Response
    {
        $preparedListCategory = [];

        foreach ($categoryRepository->findActiveNavigationRows() as $category) {
            $preparedListCategory[] = [
                'title' => $category['title'],
                'url' => $this->urlGenerator->generate('main_category_show', ['slug' => $category['slug']], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->render('catalog/navigation/_menu_nav_item.twig', [
            'nav_categories' => $preparedListCategory,
            'isActiveItemMenu' => $isActiveItemMenu,
        ]);
    }
}
