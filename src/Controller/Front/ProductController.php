<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\ConversionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductController extends AbstractController
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

    #[Route('/product/{identifier}', name: 'main_product_show')]
    #[Route('/product', name: 'main_product_show_blank')]
    public function show(string $identifier): Response
    {
        try {
            $product = $this->findVisibleProduct([
                'uuid' => $identifier,
                'isPublished' => true,
                'isDeleted' => false,
            ]);
        } catch (ConversionException $e) {
            $product = null;
        }

        if (!$product) {
            try {
                $product = $this->findVisibleProduct([
                    'slug' => $identifier,
                    'isPublished' => true,
                    'isDeleted' => false,
                ]);
            } catch (ConversionException $e) {
                $product = null;
            }
        }

        if (!$product) {
            throw new NotFoundHttpException();
        }

        $canonicalLink = $this->generateUrl('main_product_show', ['identifier' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('front/product/show.html.twig', [
            'product' => $product,
            'canonicalLink' => $canonicalLink,
        ]);
    }

    /**
     * @param array<string, bool|string> $criteria
     */
    private function findVisibleProduct(array $criteria): ?Product
    {
        return $this->productRepository->findOneBy($criteria);
    }
}
