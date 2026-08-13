<?php

declare(strict_types=1);

namespace App\SeoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RobotsTxtController extends AbstractController
{
    #[Route('/robots.txt', name: 'main_robots.txt')]
    public function index(): Response
    {
        return $this->render('@Seo/robots.txt.twig', [
            'sitemap' => $this->generateUrl('main_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
