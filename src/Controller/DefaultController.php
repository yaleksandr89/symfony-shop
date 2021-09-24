<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        $em = $this->getDoctrine()->getManager();
        $productList = $em->getRepository(Product::class)->findAll();
        return $this->render('front/default/index.html.twig', [
            'productList' => $productList
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/product-add', name: 'product_add', methods: 'GET')]
    public function productAdd(): Response
    {
        $product = new Product();
        $product->setTitle('Product ' . random_int(1, 100));
        $product->setDescription('desc product ...');
        $product->setPrice('10');
        $product->setQuantity(1);

        $em = $this->getDoctrine()->getManager();
        $em->persist($product);
        $em->flush();

        return $this->redirectToRoute('homepage');
    }
}
