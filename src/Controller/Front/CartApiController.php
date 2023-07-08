<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Repository\CartProductRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api", name="main_api_")
 */
class CartApiController extends AbstractController
{
    private Doctrine $doctrine;

    /**
     * @required
     */
    public function setDoctrine(Doctrine $doctrine): CartApiController
    {
        $this->doctrine = $doctrine;

        return $this;
    }

    /**
     * @Route("/cart", methods="POST", name="cart_save")
     */
    public function saveCart(
        Request $request,
        CartRepository $cartRepository,
        CartProductRepository $cartProductRepository,
        ProductRepository $productRepository
    ): JsonResponse {
        $manager = $this->doctrine->getManager();
        $cartToken = $request->cookies->get('CART_TOKEN');
        $productUuid = $request->request->get('productId');

        /** @var Product $product */
        $product = $productRepository->findById($productUuid);

        /** @var Cart|null $cart */
        $cart = $cartRepository->findOneBy(['token' => $cartToken]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setToken($cartToken);
        }

        /** @var CartProduct|null $cartProduct */
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
                'test' => 123,
            ],
        ]);
    }
}
