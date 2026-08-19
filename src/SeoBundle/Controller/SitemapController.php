<?php

declare(strict_types=1);

namespace App\SeoBundle\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private string $defaultLocale,
    ) {
    }

    #[Route('/sitemap.xml', name: 'main_sitemap')]
    public function index(): Response
    {
        $urls = [[
            'loc' => $this->generateUrl('main_homepage', [
                '_locale' => $this->defaultLocale,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]];

        foreach ($this->categoryRepository->findSitemapRows() as $category) {
            $urls[] = [
                'loc' => $this->generateUrl('main_category_show', [
                    '_locale' => $this->defaultLocale,
                    'slug' => $category['slug'],
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        foreach ($this->productRepository->findSitemapRows() as $product) {
            $urls[] = [
                'loc' => $this->generateUrl('main_product_show', [
                    '_locale' => $this->defaultLocale,
                    'identifier' => $product['slug'],
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $this->render('@Seo/sitemap.xml.twig', [
            'urls' => $urls,
        ]);
    }
}
