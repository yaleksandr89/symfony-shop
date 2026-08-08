<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CartRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'main_cart_show')]
    public function show(Request $request, CartRepository $cartRepository): Response
    {
        $cartToken = $request->cookies->get('CART_TOKEN');
        $cart = $cartRepository->findOneBy(['token' => $cartToken]);

        return $this->render('front/cart/show.html.twig', [
            'cart' => $cart,
        ]);
    }
}
