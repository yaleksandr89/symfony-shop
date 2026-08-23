<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commerce\ApiPlatform;

use App\Account\Repository\UserRepository;
use App\Commerce\Cart\TokenGenerator;
use App\Entity\Cart;
use App\Entity\User;
use App\Tests\Functional\ApiPlatform\ResourceTestUtils;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class CartResourceTest extends ResourceTestUtils
{
    #[TestDox('Прямой GET корзин сохраняет изоляцию по токену и требует явный административный контекст')]
    public function testDirectReadsRemainTokenScopedUnlessVerifiedAdminRequestsAdminContext(): void
    {
        $client = self::createClient();
        [$ownCart, $foreignCart] = $this->createCartReadContext();
        $ownCartId = $ownCart->getId();
        $foreignCartId = $foreignCart->getId();
        $ownToken = $ownCart->getToken();
        self::assertNotNull($ownCartId);
        self::assertNotNull($foreignCartId);
        self::assertIsString($ownToken);
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $ownToken));

        $ownCollection = $this->requestCartCollection($client);
        self::assertContains($ownCartId, array_column($ownCollection['member'], 'id'));
        self::assertNotContains($foreignCartId, array_column($ownCollection['member'], 'id'));

        $client->request('GET', '/api/carts/'.$ownCartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($ownCartId, $this->getResponseDecodedContent($client)['id']);

        $client->request('GET', '/api/carts/'.$foreignCartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->getCookieJar()->clear();
        $missingTokenCollection = $this->requestCartCollection($client);
        self::assertSame([], $missingTokenCollection['member']);
        self::assertSame(0, $missingTokenCollection['totalItems']);

        $client->request('GET', '/api/carts/'.$ownCartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $ownToken));
        $adminTokenCollection = $this->requestCartCollection($client);
        self::assertContains($ownCartId, array_column($adminTokenCollection['member'], 'id'));
        self::assertNotContains($foreignCartId, array_column($adminTokenCollection['member'], 'id'));

        $client->request('GET', '/api/carts/'.$foreignCartId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $adminCollection = $this->requestCartCollection($client, '?context=admin');
        self::assertContains($ownCartId, array_column($adminCollection['member'], 'id'));
        self::assertContains($foreignCartId, array_column($adminCollection['member'], 'id'));

        $client->request('GET', '/api/carts/'.$foreignCartId.'?context=admin', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($foreignCartId, $this->getResponseDecodedContent($client)['id']);
    }

    #[TestDox('POST корзины создаёт разные корректные токены без cookie владения')]
    public function testCartPostGeneratesDistinctValidTokensWhenOwnershipCookiesAreMissing(): void
    {
        $client = self::createClient();
        $firstCart = $this->postCart($client);
        $client->getCookieJar()->clear();
        $secondCart = $this->postCart($client);

        $firstToken = $this->persistedCart($firstCart['token'])->getToken();
        $secondToken = $this->persistedCart($secondCart['token'])->getToken();
        self::assertIsString($firstToken);
        self::assertIsString($secondToken);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $firstToken);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $secondToken);
        self::assertNotSame($firstToken, $secondToken);
    }

    #[TestDox('POST корзины повторно использует корректную cookie владения')]
    public function testCartPostReusesAValidOwnershipCookie(): void
    {
        $token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));

        $cart = $this->postCart($client);

        self::assertSame($token, $this->persistedCart($cart['token'])->getToken());
    }

    #[TestDox('POST корзины заменяет некорректную cookie владения')]
    public function testCartPostReplacesAnInvalidOwnershipCookie(): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', 'wrong-token'));

        $cart = $this->postCart($client);
        $token = $this->persistedCart($cart['token'])->getToken();
        self::assertIsString($token);

        self::assertMatchesRegularExpression('/\A[0-9a-f]{32}\z/', $token);
        self::assertNotSame('wrong-token', $token);
    }

    #[TestDox('POST корзины предпочитает токен из cookie токену из тела запроса')]
    public function testCartPostUsesOwnershipCookieInsteadOfBodyToken(): void
    {
        $cookieToken = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $bodyToken = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $cookieToken));

        $cart = $this->postCart($client, ['token' => $bodyToken]);

        self::assertSame($cookieToken, $this->persistedCart($cart['token'])->getToken());
        self::assertNotSame($bodyToken, $this->persistedCart($cart['token'])->getToken());
    }

    #[TestDox('Повторный POST корзины возвращает конфликт без изменения сохранённых данных')]
    public function testDuplicateCartPostReturnsConflictWithoutChangingPersistedState(): void
    {
        $token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));
        $firstCart = $this->postCart($client);
        $firstId = $this->persistedCart($firstCart['token'])->getId();
        self::assertIsInt($firstId);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));
        $client->request('POST', '/api/carts', [], [], self::REQUEST_HEADERS, '{}');

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertSame(1, $entityManager->getRepository(Cart::class)->count(['token' => $token]));
        $persistedCart = $entityManager->find(Cart::class, $firstId);
        self::assertInstanceOf(Cart::class, $persistedCart);
        self::assertSame($firstId, $persistedCart->getId());
        self::assertSame($token, $persistedCart->getToken());
    }

    #[TestDox('Владелец по токену может удалить свою корзину')]
    public function testMatchingTokenCanDeleteOwnCart(): void
    {
        $client = self::createClient();
        $cart = $this->postCart($client);
        $persistedCart = $this->persistedCart($cart['token']);
        $cartId = $persistedCart->getId();
        self::assertNotNull($cartId);
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $cart['token']));

        $client->request('DELETE', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertNull($entityManager->find(Cart::class, $cartId));
    }

    #[TestDox('Чужой токен не позволяет удалить корзину и сохраняет её')]
    public function testForeignTokenCannotDeleteCart(): void
    {
        $client = self::createClient();
        $cart = $this->postCart($client);
        $persistedCart = $this->persistedCart($cart['token']);
        $cartId = $persistedCart->getId();
        self::assertNotNull($cartId);
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'));

        $client->request('DELETE', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertInstanceOf(Cart::class, $entityManager->find(Cart::class, $cartId));
    }

    #[TestDox('Без токена нельзя удалить корзину и она остаётся сохранённой')]
    public function testMissingTokenCannotDeleteCart(): void
    {
        $client = self::createClient();
        $cart = $this->postCart($client);
        $persistedCart = $this->persistedCart($cart['token']);
        $cartId = $persistedCart->getId();
        self::assertNotNull($cartId);
        $client->getCookieJar()->clear();

        $client->request('DELETE', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertInstanceOf(Cart::class, $entityManager->find(Cart::class, $cartId));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{token: string}
     */
    private function postCart(AbstractBrowser $client, array $payload = []): array
    {
        $client->request(
            'POST',
            '/api/carts',
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $document = $this->getResponseDecodedContent($client);
        self::assertIsString($document['token']);

        return ['token' => $document['token']];
    }

    private function persistedCart(string $token): Cart
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $cart = $entityManager->getRepository(Cart::class)->findOneBy(['token' => $token]);
        self::assertInstanceOf(Cart::class, $cart);

        return $cart;
    }

    /** @return array{Cart, Cart} */
    private function createCartReadContext(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $ownCart = (new Cart())->setToken(TokenGenerator::generateToken());
        $foreignCart = (new Cart())->setToken(TokenGenerator::generateToken());
        $entityManager->persist($ownCart);
        $entityManager->persist($foreignCart);
        $entityManager->flush();

        return [$ownCart, $foreignCart];
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    /** @return array<string, mixed> */
    private function requestCartCollection(AbstractBrowser $client, string $query = ''): array
    {
        $client->request('GET', '/api/carts'.$query, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return $this->getResponseDecodedContent($client);
    }
}
