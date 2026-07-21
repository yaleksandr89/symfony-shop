<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Money\DecimalMoney;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class OrderCheckoutResourceTest extends ResourceTestUtils
{
    private const URI = '/api/orders';

    public function testAnonymousCheckoutIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 2]]);
        $counts = $this->getOrderCounts();
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testRegularUserCanCheckoutOwnNonEmptyCart(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 2], ['7.50', 3]]);
        $counts = $this->getOrderCounts();
        $user = $this->getUser(UserFixtures::USER_1_EMAIL);
        $userId = $user->getId();
        self::assertNotNull($userId);
        $client->loginUser($user, 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertArrayNotHasKey('token', $this->getResponseDecodedContent($client));
        self::assertEmailCount(2);
        $clientMessage = self::getMailerMessage(0);
        self::assertNotNull($clientMessage);
        self::assertEmailAddressContains($clientMessage, 'to', UserFixtures::USER_1_EMAIL);

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        self::assertSame($counts['orders'] + 1, $entityManager->getRepository(Order::class)->count([]));
        self::assertSame($counts['orderProducts'] + 2, $entityManager->getRepository(OrderProduct::class)->count([]));

        /** @var Order|null $order */
        $order = $entityManager->getRepository(Order::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(Order::class, $order);
        self::assertSame($userId, $order->getOwner()?->getId());
        self::assertSame(OrderStaticStorage::ORDER_STATUS_CREATED, $order->getStatus());
        self::assertSame('42.50', $order->getTotalPrice());

        $actualLines = [];
        foreach ($order->getOrderProducts() as $orderProduct) {
            $price = $orderProduct->getPricePerOne();
            self::assertNotNull($price);
            $actualLines[] = [
                'productId' => $orderProduct->getProduct()?->getId(),
                'quantity' => $orderProduct->getQuantity(),
                'priceCents' => DecimalMoney::toCents($price),
            ];
        }
        usort($actualLines, static fn (array $left, array $right): int => $left['productId'] <=> $right['productId']);
        $expectedLines = $cart['lines'];
        foreach ($expectedLines as &$expectedLine) {
            $expectedLine['priceCents'] = DecimalMoney::toCents($expectedLine['price']);
            unset($expectedLine['price']);
        }
        unset($expectedLine);
        usort($expectedLines, static fn (array $left, array $right): int => $left['productId'] <=> $right['productId']);
        self::assertSame($expectedLines, $actualLines);
        $this->assertCartPersisted($cart);
    }

    public function testRegularUserCannotCheckoutForeignCart(): void
    {
        $client = self::createClient();
        $ownCart = $this->createCart([['10.00', 1]]);
        $foreignCart = $this->createCart([['20.00', 2]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $ownCart['token']);

        $this->requestCheckout($client, ['cartId' => $foreignCart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$ownCart, $foreignCart]);
    }

    public function testCheckoutWithoutCartTokenIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testCheckoutWithWrongCartTokenIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, 'wrong-token');

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testCheckoutWithoutCartIdIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testInvalidCartIdsAreRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        foreach (['abc', 0, -1] as $invalidCartId) {
            $counts = $this->getOrderCounts();

            $this->requestCheckout($client, ['cartId' => $invalidCartId]);

            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
            $this->assertNoCheckoutSideEffects($counts, [$cart]);
        }
    }

    public function testCheckoutWithNonexistentCartIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $existingCart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $existingCart['token']);

        $this->requestCheckout($client, ['cartId' => $existingCart['id'] + 100000]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$existingCart]);
    }

    public function testCheckoutWithEmptyCartIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testAdminCannotBypassForeignCartTokenRequirement(): void
    {
        $client = self::createClient();
        $adminCart = $this->createCart([['10.00', 1]]);
        $foreignCart = $this->createCart([['20.00', 2]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $this->setCartToken($client, $adminCart['token']);

        $this->requestCheckout($client, ['cartId' => $foreignCart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$adminCart, $foreignCart]);
    }

    /**
     * @param list<array{0: string, 1: int}> $definitions
     *
     * @return array{id: int, token: string, lines: list<array{productId: int, quantity: int, price: string}>}
     */
    private function createCart(array $definitions): array
    {
        $entityManager = $this->getEntityManager();
        $suffix = str_replace('.', '', uniqid('', true));
        $token = 'checkout-'.$suffix;
        $cart = (new Cart())->setToken($token);
        $lines = [];

        foreach ($definitions as [$price, $quantity]) {
            $product = (new Product())
                ->setTitle('Checkout product '.$suffix.'-'.$quantity)
                ->setPrice($price)
                ->setQuantity(100);
            $line = (new CartProduct())
                ->setProduct($product)
                ->setQuantity($quantity);
            $cart->addCartProduct($line);
            $entityManager->persist($product);
        }

        $entityManager->persist($cart);
        $entityManager->flush();

        $cartId = $cart->getId();
        self::assertNotNull($cartId);
        foreach ($cart->getCartProducts() as $line) {
            $productId = $line->getProduct()?->getId();
            self::assertNotNull($productId);
            self::assertNotNull($line->getQuantity());
            self::assertNotNull($line->getProduct()?->getPrice());
            $lines[] = [
                'productId' => $productId,
                'quantity' => $line->getQuantity(),
                'price' => $line->getProduct()->getPrice(),
            ];
        }

        return ['id' => $cartId, 'token' => $token, 'lines' => $lines];
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

    /**
     * @param array{cartId?: int|string} $payload
     */
    private function requestCheckout(AbstractBrowser $client, array $payload): void
    {
        $client->request(
            'POST',
            self::URI,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array{orders: int, orderProducts: int} $counts
     * @param list<array{id: int, token: string, lines: list<array{productId: int, quantity: int, price: string}>}> $carts
     */
    private function assertNoCheckoutSideEffects(array $counts, array $carts): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        self::assertSame($counts['orders'], $entityManager->getRepository(Order::class)->count([]));
        self::assertSame($counts['orderProducts'], $entityManager->getRepository(OrderProduct::class)->count([]));
        self::assertEmailCount(0);

        foreach ($carts as $cart) {
            $this->assertCartPersisted($cart);
        }
    }

    /** @param array{id: int, token: string, lines: list<array{productId: int, quantity: int, price: string}>} $expected */
    private function assertCartPersisted(array $expected): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        /** @var Cart|null $cart */
        $cart = $entityManager->find(Cart::class, $expected['id']);
        self::assertInstanceOf(Cart::class, $cart);
        self::assertSame($expected['token'], $cart->getToken());

        $actualLines = [];
        foreach ($cart->getCartProducts() as $line) {
            $price = $line->getProduct()?->getPrice();
            self::assertNotNull($price);
            $actualLines[] = [
                'productId' => $line->getProduct()?->getId(),
                'quantity' => $line->getQuantity(),
                'priceCents' => DecimalMoney::toCents($price),
            ];
        }
        usort($actualLines, static fn (array $left, array $right): int => $left['productId'] <=> $right['productId']);
        $expectedLines = $expected['lines'];
        foreach ($expectedLines as &$expectedLine) {
            $expectedLine['priceCents'] = DecimalMoney::toCents($expectedLine['price']);
            unset($expectedLine['price']);
        }
        unset($expectedLine);
        usort($expectedLines, static fn (array $left, array $right): int => $left['productId'] <=> $right['productId']);
        self::assertSame($expectedLines, $actualLines);
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
