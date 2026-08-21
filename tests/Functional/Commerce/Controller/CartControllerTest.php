<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commerce\Controller;

use App\Commerce\Cart\TokenGenerator;
use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[\PHPUnit\Framework\Attributes\Group(name: 'functional')]
class CartControllerTest extends WebTestCase
{
    #[TestDox('HTML-страница корзины не загружает сущность корзины')]
    public function testCartHtmlDoesNotLoadCartEntity(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/ru/cart');

        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertLessThanOrEqual(1, $collector->getQueryCount());
    }

    #[TestDox('Устаревшие маршруты создания удалены и не меняют данные')]
    public function testLegacyCreateRoutesAreRemovedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $user = $this->getUser(UserFixtures::USER_1_EMAIL);
        $client->loginUser($user, 'website');
        $cart = $this->createCart();
        $counts = $this->getOrderCounts();
        $this->setCartToken($client, $cart['token']);

        /** @var Router $router */
        $router = self::getContainer()->get('router');
        self::assertNull($router->getRouteCollection()->get('main_cart_create'));

        foreach (['/ru/cart/create', '/en/cart/create'] as $url) {
            $client->request('GET', $url);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        self::assertSame($counts['orders'], $entityManager->getRepository(Order::class)->count([]));
        self::assertSame($counts['orderProducts'], $entityManager->getRepository(OrderProduct::class)->count([]));
        self::assertEmailCount(0);

        self::assertInstanceOf(Cart::class, $entityManager->find(Cart::class, $cart['id']));
        self::assertInstanceOf(CartProduct::class, $entityManager->find(CartProduct::class, $cart['cartProductId']));
    }

    #[TestDox('Устаревшие API-маршруты удалены и не меняют данные')]
    public function testLegacyApiRoutesAreRemovedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart();
        $counts = $this->getCartCounts();
        $this->setCartToken($client, $cart['token']);

        /** @var Router $router */
        $router = self::getContainer()->get('router');
        self::assertNull($router->getRouteCollection()->get('main_api_cart_save'));

        foreach (['/ru/api/cart', '/en/api/cart'] as $url) {
            $client->request('POST', $url, ['productId' => $cart['productUuid']]);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        self::assertSame($counts['carts'], $entityManager->getRepository(Cart::class)->count([]));
        self::assertSame($counts['cartProducts'], $entityManager->getRepository(CartProduct::class)->count([]));

        $persistedCart = $entityManager->find(Cart::class, $cart['id']);
        $persistedCartProduct = $entityManager->find(CartProduct::class, $cart['cartProductId']);
        self::assertInstanceOf(Cart::class, $persistedCart);
        self::assertInstanceOf(CartProduct::class, $persistedCartProduct);
        self::assertSame($cart['quantity'], $persistedCartProduct->getQuantity());
    }

    /** @return array{id: int, cartProductId: int, productUuid: string, quantity: int, token: string} */
    private function createCart(): array
    {
        $entityManager = $this->getEntityManager();
        $suffix = str_replace('.', '', uniqid('', true));
        $token = TokenGenerator::generateToken();
        $product = (new Product())
            ->setTitle('Removed legacy cart route product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(10)
            ->setIsPublished(true);
        $cart = (new Cart())->setToken($token);
        $cartProduct = (new CartProduct())
            ->setProduct($product)
            ->setQuantity(2);
        $cart->addCartProduct($cartProduct);

        $entityManager->persist($product);
        $entityManager->persist($cart);
        $entityManager->flush();

        $cartId = $cart->getId();
        $cartProductId = $cartProduct->getId();
        self::assertNotNull($cartId);
        self::assertNotNull($cartProductId);

        return [
            'id' => $cartId,
            'cartProductId' => $cartProductId,
            'productUuid' => (string) $product->getUuid(),
            'quantity' => $cartProduct->getQuantity(),
            'token' => $token,
        ];
    }

    /** @return array{carts: int, cartProducts: int} */
    private function getCartCounts(): array
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        return [
            'carts' => $entityManager->getRepository(Cart::class)->count([]),
            'cartProducts' => $entityManager->getRepository(CartProduct::class)->count([]),
        ];
    }

    /** @return array{orders: int, orderProducts: int} */
    private function getOrderCounts(): array
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        return [
            'orders' => $entityManager->getRepository(Order::class)->count([]),
            'orderProducts' => $entityManager->getRepository(OrderProduct::class)->count([]),
        ];
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function setCartToken(AbstractBrowser $client, string $token): void
    {
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));
    }
}
