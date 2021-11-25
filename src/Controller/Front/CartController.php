<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Repository\CartRepository;
use App\Utils\Manager\OrderManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        $cartToken = $request->cookies->get('CART_TOKEN');
        $cart = $cartRepository->findOneBy(['token' => $cartToken]);

        return $this->render('front/cart/show.html.twig', [
            'cart' => $cart,
        ]);
    }

    /**
     * @Route("/cart/create", name="main_cart_create")
     * @param Request $request
     * @param OrderManager $orderManager
     * @return Response
     */
    public function create(Request $request, OrderManager $orderManager): Response
    {
        $cartToken = $request->cookies->get('CART_TOKEN');
        $user = $this->getUser();
dd($user);
        $orderManager->createOrderFromCartByToken($cartToken, $user);

        $redirectUrl = $this->generateUrl('main_cart_show');

        // Пример удаления куки 'CART_TOKEN' через контроллер
        $response = new RedirectResponse($redirectUrl);
        $response->headers->clearCookie('CART_TOKEN', '/', null);

        return $response;
//        if (!$user) {
//            $this->addFlash('warning', 'Please log in to the site!');
//            return $this->redirectToRoute('main_homepage');
//        }
//
//        $orderManager->createOrderFromCartBySessionId($phpSessionId, $user);
//
//        $this->addFlash('success', 'The order successfully created');
//        return $this->redirectToRoute('main_cart_show');
    }
}
