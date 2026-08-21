<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commerce\ApiPlatform;

use App\Entity\Cart;
use App\Tests\Functional\ApiPlatform\ResourceTestUtils;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class CartResourceTest extends ResourceTestUtils
{
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
}
