<?php

namespace App\Controller\Front;

use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    /**
     * @Route("/cart", name="main_cart_show")
     * @param Request $request
     * @param CartRepository $cartRepository
     * @return Response
     */
    public function show(Request $request, CartRepository $cartRepository): Response
    {
        $phpSessionId = $request->cookies->get('PHPSESSID');
        $cart = $cartRepository->findOneBy(['sessionId' => $phpSessionId]);

        return $this->render('front/cart/show.html.twig', [
            'cart' => $cart,
        ]);
    }
}
