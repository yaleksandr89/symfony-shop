<?php

declare(strict_types=1);

namespace App\SeoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'main_sitemap')]
    public function index(): Response
    {
        $mainPageInfo = [
            'loc' => $this->generateUrl('main_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];

        return $this->render('@Seo/sitemap.xml.twig', [
            'data' => $mainPageInfo,
        ]);
    }
}
