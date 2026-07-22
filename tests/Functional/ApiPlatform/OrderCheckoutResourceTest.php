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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testCheckoutWithForeignTokenDoesNotRevealCartAvailability(): void
    {
        $client = self::createClient();
        $ownCart = $this->createCart([['10.00', 1]]);
        $foreignCart = $this->createCart([['20.00', 2]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $ownCart['token']);

        $this->requestCheckout($client, ['cartId' => $foreignCart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertCheckoutUnavailableResponse($client);
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

    #[DataProvider('invalidCartIdPayloads')]
    public function testMissingOrNonPositiveCartIdIsRejectedByValidationWithoutSideEffects(array $payload): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testStringCartIdIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $counts = $this->getOrderCounts();
        $this->requestCheckout($client, ['cartId' => '123']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
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
        $this->assertCheckoutUnavailableResponse($client);
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
        $this->assertCheckoutUnavailableResponse($client);
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

    public function testAdminCannotBypassMissingCartTokenRequirement(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    #[DataProvider('forbiddenOrderFields')]
    public function testForbiddenOrderFieldsAreRejectedBeforeCheckoutSideEffects(string $field, mixed $value): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id'], $field => $value]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testCombinedMassAssignmentPayloadIsRejectedBeforeCheckoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, [
            'cartId' => $cart['id'],
            'owner' => '/api/users/1',
            'status' => 999,
            'totalPrice' => '0.01',
            'createdAt' => '2000-01-01T00:00:00+00:00',
            'updatedAt' => '2000-01-01T00:00:00+00:00',
            'isDeleted' => true,
            'orderProducts' => [],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testMalformedJsonIsRejectedWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 1]]);
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckoutRaw($client, '{"cartId":');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
    }

    public function testCheckoutRejectsPersistedZeroQuantityWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 2]]);
        $line = $this->getOnlyCartProduct($cart['id']);
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        $line->setQuantity(0);
        $this->getEntityManager()->flush();
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertCheckoutUnavailableResponse($client);
        $this->assertNoCheckoutSideEffects($counts, []);
        $this->assertCartProductQuantity($lineId, 0);
    }

    public function testCheckoutRejectsLineAfterProductStockIsLoweredWithoutSideEffects(): void
    {
        $client = self::createClient();
        $cart = $this->createCart([['10.00', 2]]);
        $line = $this->getOnlyCartProduct($cart['id']);
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        $product = $line->getProduct();
        self::assertInstanceOf(Product::class, $product);
        $product->setQuantity(1);
        $this->getEntityManager()->flush();
        $counts = $this->getOrderCounts();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $cart['token']);

        $this->requestCheckout($client, ['cartId' => $cart['id']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertCheckoutUnavailableResponse($client);
        $this->assertNoCheckoutSideEffects($counts, [$cart]);
        $this->assertCartProductQuantity($lineId, 2);
    }

    public function testCheckoutOpenApiInputSchemaOnlyAllowsRequiredCartId(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs.json', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        $schemaReference = $document['paths']['/api/orders']['post']['requestBody']['content']['application/json']['schema'];
        self::assertSame('#/components/schemas/Order.CheckoutOrderInput', $schemaReference['$ref']);
        $schema = $document['components']['schemas']['Order.CheckoutOrderInput'];
        self::assertSame(['cartId'], array_keys($schema['properties']));
        self::assertSame(['cartId'], $schema['required']);
        self::assertFalse($schema['additionalProperties']);
    }

    /** @return iterable<string, array{array{cartId?: int|null}}> */
    public static function invalidCartIdPayloads(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['cartId' => null]];
        yield 'zero' => [['cartId' => 0]];
        yield 'negative' => [['cartId' => -1]];
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function forbiddenOrderFields(): iterable
    {
        yield 'owner' => ['owner', '/api/users/1'];
        yield 'status' => ['status', 999];
        yield 'total price' => ['totalPrice', '0.01'];
        yield 'created at' => ['createdAt', '2000-01-01T00:00:00+00:00'];
        yield 'updated at' => ['updatedAt', '2000-01-01T00:00:00+00:00'];
        yield 'is deleted' => ['isDeleted', true];
        yield 'order products' => ['orderProducts', []];
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
     * @param array<string, mixed> $payload
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

    private function requestCheckoutRaw(AbstractBrowser $client, string $content): void
    {
        $client->request('POST', self::URI, [], [], self::REQUEST_HEADERS, $content);
    }

    private function assertCheckoutUnavailableResponse(AbstractBrowser $client): void
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('Checkout cart is unavailable.', $content);
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

    private function getOnlyCartProduct(int $cartId): CartProduct
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $cart = $entityManager->find(Cart::class, $cartId);
        self::assertInstanceOf(Cart::class, $cart);
        $line = $cart->getCartProducts()->first();
        self::assertInstanceOf(CartProduct::class, $line);

        return $line;
    }

    private function assertCartProductQuantity(int $lineId, int $quantity): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $line = $entityManager->find(CartProduct::class, $lineId);
        self::assertInstanceOf(CartProduct::class, $line);
        self::assertSame($quantity, $line->getQuantity());
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
