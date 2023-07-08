<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
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

    public function showSimilarProducts(ProductRepository $productRepository, int $productCount = 2, int $categoryId = null): Response
    {
        $products = $productRepository->findByCategoryAndCount($categoryId, $productCount);

        return $this->render('front/_embed/_similar_products.html.twig', [
            'products' => $products,
        ]);
    }

    public function showHeaderMenu(CategoryRepository $categoryRepository, ?string $isActiveItemMenu): Response
    {
        $preparedListCategory = [];

        /** @var Category $category */
        foreach ($categoryRepository->findActiveCategoryWithJoinProduct() as $category) {
            $preparedListCategory[] = [
                'title' => $category->getTitle(),
                'url' => $this->urlGenerator->generate('main_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->render('front/_embed/_menu/_menu_nav_item.twig', [
            'nav_categories' => $preparedListCategory,
            'isActiveItemMenu' => $isActiveItemMenu,
        ]);
    }
}
