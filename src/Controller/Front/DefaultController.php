<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;

class DefaultController extends AbstractController
{
    private UrlGeneratorInterface $urlGenerator;

    #[Required]
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): DefaultController
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    #[Route('/', name: 'main_homepage')]
    public function index(Request $request, CategoryRepository $categoryRepository): Response
    {
        $preparedListCategory = [];
        $baseProductImagDir = $this->getParameter('product_images_url');

        $candidatesByCategory = [];
        foreach ($categoryRepository->findHomepageBannerCandidates() as $candidate) {
            $candidatesByCategory[$candidate['categoryId']][] = $candidate;
        }
        foreach ($candidatesByCategory as $candidates) {
            $candidate = $candidates[array_rand($candidates)];
            $preparedListCategory[] = [
                'title' => $candidate['categoryTitle'],
                'url' => $this->urlGenerator->generate('main_category_show', ['slug' => $candidate['categorySlug']], UrlGeneratorInterface::ABSOLUTE_URL),
                'rand_product_img' => $request->getUriForPath($baseProductImagDir)."/{$candidate['productId']}/{$candidate['cover']['filenameBig']}",
            ];
        }

        return $this->render('front/default/index.html.twig', [
            'categories' => $preparedListCategory,
        ]);
    }
}
