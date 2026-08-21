<?php

declare(strict_types=1);

namespace App\Catalog\Controller;

use App\Catalog\Repository\ProductRepository;
use App\Entity\Product;
use Doctrine\DBAL\Types\ConversionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductController extends AbstractController
{
    public function __construct(private readonly ProductRepository $productRepository)
    {
    }

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

        return $this->render('catalog/product/show.html.twig', [
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
