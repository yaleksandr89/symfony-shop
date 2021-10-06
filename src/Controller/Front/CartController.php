<?php

namespace App\Controller\Front;

use App\Repository\CartRepository;
use App\Utils\Manager\OrderManager;
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
        $phpSessionId = $this->getPhpSessionId($request);
        $cart = $cartRepository->findOneBy(['sessionId' => $phpSessionId]);

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
        $phpSessionId = $this->getPhpSessionId($request);
        $user = $this->getUser();
        $orderManager->createOrderFromCartBySessionId($phpSessionId, $user);

        return $this->redirectToRoute('main_cart_show');
    }

    /**
     * @param Request $request
     * @return string|null
     */
    private function getPhpSessionId(Request $request): ?string
    {
        return $request->cookies->get('PHPSESSID');
    }
}
