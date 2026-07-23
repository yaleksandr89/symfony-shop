<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Repository\CartProductRepository;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Utils\Generator\TokenGenerator;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Service\Attribute\Required;

#[Route('/api', name: 'main_api_')]
class CartApiController extends AbstractController
{
    private Doctrine $doctrine;

    #[Required]
    public function setDoctrine(Doctrine $doctrine): CartApiController
    {
        $this->doctrine = $doctrine;

        return $this;
    }

    #[Route('/cart', name: 'cart_save', methods: ['POST'])]
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
            $cart->setToken(
                is_string($cartToken) && preg_match('/\A[0-9a-f]{32}\z/', $cartToken)
                    ? $cartToken
                    : TokenGenerator::generateToken()
            );
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
