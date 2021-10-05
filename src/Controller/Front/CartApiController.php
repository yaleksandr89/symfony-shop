<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Repository\CartProductRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="main_api_")
 */
class CartApiController extends AbstractController
{
    /**
     * @Route("/cart", methods="POST", name="cart_save")
     * @param Request $request
     * @param CartRepository $cartRepository
     * @param CartProductRepository $cartProductRepository
     * @param ProductRepository $productRepository
     * @return JsonResponse
     */
    public function saveCart(
        Request $request,
        CartRepository $cartRepository,
        CartProductRepository $cartProductRepository,
        ProductRepository $productRepository
    ): JsonResponse {
        $manager = $this->getDoctrine()->getManager();
        $phpSessionId = $request->cookies->get('PHPSESSID');
        $productUuid = $request->request->get('productId');

        /** @var Product $product */
        $product = $productRepository->findById($productUuid);

        /** @var Cart $cart */
        $cart = $cartRepository->findOneBy(['sessionId' => $phpSessionId]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setSessionId($phpSessionId);
        }

        /** @var CartProduct $cartProduct */
        $cartProduct = $cartProductRepository->findOneBy(['cart' => $cart, 'product' => $product]);
        if (!$cartProduct) {
            $cartProduct = new CartProduct();
            $cartProduct->setCart($cart);
            $cartProduct->setProduct($product);
            $cartProduct->setQuantity(1);

            $cart->addCartProduct($cartProduct);
        } else {
            $quantity = $cartProduct->getQuantity() + 1;
            $cartProduct->setQuantity($quantity);
        }

        $manager->persist($cart);
        $manager->persist($cartProduct);
        $manager->flush();

        return new JsonResponse([
            'success' => false,
            'data' => [
                'test' => 123
            ],
        ]);
    }
}
