<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Product;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductController extends AbstractController
{
    // >>> Autowiring

    private Doctrine $doctrine;

    /**
     * @required
     */
    public function setDoctrine(Doctrine $doctrine): ProductController
    {
        $this->doctrine = $doctrine;

        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/product/{identifier}", name="main_product_show")
     * @Route("/product", name="main_product_show_blank")
     */
    public function show(string $identifier): Response
    {
        try {
            $product = $this->doctrine
                ->getRepository(Product::class)
                ->findOneBy(['uuid' => $identifier]);
        } catch (ConversionException $e) {
            $product = null;
        }

        if (!$product) {
            try {
                $product = $this->doctrine
                    ->getRepository(Product::class)
                    ->findOneBy(['slug' => $identifier]);
            } catch (ConversionException $e) {
                $product = null;
            }
        }

        if (!$product) {
            throw new NotFoundHttpException();
        }

        if (true === $product->getIsDeleted()) {
            $this->addFlash('warning', "The product {$product->getTitle()} not found!");

            return $this->redirectToRoute('main_homepage');
        }

        $canonicalLink = $this->generateUrl('main_product_show', ['identifier' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('front/product/show.html.twig', [
            'product' => $product,
            'canonicalLink' => $canonicalLink,
        ]);
    }
}
