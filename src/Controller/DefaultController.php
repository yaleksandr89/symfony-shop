<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Product;
use App\Form\EditProductFormType;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    /**
     * @Route("/", name="main_homepage")
     * @return Response
     */
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
     * @Route("/product-add_old", name="product_add_old", methods="GET")
     */
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

    /**
     * @Route("/product-edit/{id}", name="product_edit", methods={"GET","POST"}, requirements={"id"="\d+"})
     * @Route("/product-add", name="product_add", methods={"GET","POST"})
     * @param Request $request
     * @param int|null $id
     * @return Response|null
     */
    public function editProduct(Request $request, int $id = null): ?Response
    {
        $em = $this->getDoctrine()->getManager();

        if ($id) {
            $product = $em->getRepository(Product::class)->find($id);
        } else {
            $product = new Product();
        }

        $form = $this->createForm(EditProductFormType::class, $product);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();

            return $this->redirectToRoute('product_edit', ['id' => $product->getId()]);
        }

        return $this->render('front/default/edit_product.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
