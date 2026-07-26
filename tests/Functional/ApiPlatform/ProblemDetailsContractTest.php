<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class ProblemDetailsContractTest extends ResourceTestUtils
{
    private const JSON_HEADERS = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];

    private const JSON_LD_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json',
    ];

    public function testValidationFailureForJsonUsesProblemDetailsWithoutSideEffects(): void
    {
        $client = self::createClient();
        $context = $this->createContext();
        $this->setCartToken($client, $context['token']);
        $counts = $this->counts();

        $this->postInvalidQuantity($client, $context, self::JSON_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertValidationProblem($this->getResponseDecodedContent($client));
        $this->assertNoSideEffects($context, $counts);
    }

    public function testValidationFailureForJsonLdUsesProblemDetailsWithoutSideEffects(): void
    {
        $client = self::createClient();
        $context = $this->createContext();
        $this->setCartToken($client, $context['token']);
        $counts = $this->counts();

        $this->postInvalidQuantity($client, $context, self::JSON_LD_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertValidationProblem($this->getResponseDecodedContent($client));
        $this->assertNoSideEffects($context, $counts);
    }

    public function testMalformedJsonUsesProblemDetailsInDebugWithoutSideEffects(): void
    {
        $client = self::createClient();
        $context = $this->createContext();
        $this->setCartToken($client, $context['token']);
        $counts = $this->counts();

        $client->request('POST', '/api/cart_products', [], [], self::JSON_HEADERS, '{"quantity":');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertProblemDetails($this->getResponseDecodedContent($client), Response::HTTP_BAD_REQUEST);
        $this->assertNoSideEffects($context, $counts);
    }

    public function testMissingProductUsesProblemDetailsInDebug(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/products/00000000-0000-4000-8000-000000000000', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $this->assertProblemDetails($this->getResponseDecodedContent($client), Response::HTTP_NOT_FOUND);
    }

    public function testMissingProductDoesNotExposeDebugFieldsWhenKernelDebugIsDisabled(): void
    {
        $client = self::createClient(['debug' => false]);

        $client->request('GET', '/api/products/00000000-0000-4000-8000-000000000000', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $document = $this->getResponseDecodedContent($client);
        $this->assertProblemDetails($document, Response::HTTP_NOT_FOUND);
        $this->assertNoDebugFields($document);
    }

    public function testSecurityProblemContractRemainsApplicationOwned(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/orders', [], [], ['HTTP_ACCEPT' => 'application/json']);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
    }

    public function testUnsupportedAcceptRemainsNotAcceptable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/products', [], [], ['HTTP_ACCEPT' => 'text/plain']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_ACCEPTABLE);
    }

    /** @return array{cartId: int, productId: int, productUuid: string, token: string, productQuantity: int} */
    private function createContext(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $token = bin2hex(random_bytes(16));
        $product = (new Product())
            ->setTitle('Problem details product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(10)
            ->setIsPublished(true);
        $cart = (new Cart())->setToken($token);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($product);
        $entityManager->persist($cart);
        $entityManager->flush();

        self::assertIsInt($cart->getId());
        self::assertIsInt($product->getId());

        return [
            'cartId' => $cart->getId(),
            'productId' => $product->getId(),
            'productUuid' => (string) $product->getUuid(),
            'token' => $token,
            'productQuantity' => $product->getQuantity(),
        ];
    }

    /** @return array{cartProducts: int, carts: int, products: int} */
    private function counts(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return [
            'cartProducts' => $entityManager->getRepository(CartProduct::class)->count([]),
            'carts' => $entityManager->getRepository(Cart::class)->count([]),
            'products' => $entityManager->getRepository(Product::class)->count([]),
        ];
    }

    private function setCartToken(AbstractBrowser $client, string $token): void
    {
        $client->getCookieJar()->set(new Cookie('CART_TOKEN', $token));
    }

    /** @param array{cartId: int, productId: int, productUuid: string, token: string, productQuantity: int} $context */
    private function postInvalidQuantity(AbstractBrowser $client, array $context, array $headers): void
    {
        $client->request('POST', '/api/cart_products', [], [], $headers, json_encode([
            'cart' => '/api/carts/'.$context['cartId'],
            'product' => '/api/products/'.$context['productUuid'],
            'quantity' => 0,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $document */
    private function assertValidationProblem(array $document): void
    {
        $this->assertProblemDetails($document, Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('violations', $document);
        self::assertIsArray($document['violations']);
        self::assertCount(1, $document['violations']);
        self::assertSame('quantity', $document['violations'][0]['propertyPath']);
        self::assertSame('Quantity must be at least 1.', $document['violations'][0]['message']);
    }

    /** @param array<string, mixed> $document */
    private function assertProblemDetails(array $document, int $status): void
    {
        self::assertArrayHasKey('type', $document);
        self::assertIsString($document['type']);
        self::assertNotSame('', $document['type']);
        self::assertArrayHasKey('title', $document);
        self::assertIsString($document['title']);
        self::assertNotSame('', $document['title']);
        self::assertSame($status, $document['status']);
        self::assertArrayHasKey('detail', $document);
        self::assertIsString($document['detail']);
        self::assertNotSame('', $document['detail']);
    }

    /** @param array<string, mixed> $document */
    private function assertNoDebugFields(array $document): void
    {
        foreach (['trace', 'file', 'line', 'class', 'exception', 'traceAsString'] as $key) {
            self::assertArrayNotHasKey($key, $document);
        }
    }

    /**
     * @param array{cartId: int, productId: int, productUuid: string, token: string, productQuantity: int} $context
     * @param array{cartProducts: int, carts: int, products: int} $counts
     */
    private function assertNoSideEffects(array $context, array $counts): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        self::assertSame($counts, $this->counts());

        $cart = $entityManager->find(Cart::class, $context['cartId']);
        $product = $entityManager->find(Product::class, $context['productId']);
        self::assertInstanceOf(Cart::class, $cart);
        self::assertInstanceOf(Product::class, $product);
        self::assertSame($context['token'], $cart->getToken());
        self::assertSame($context['productUuid'], (string) $product->getUuid());
        self::assertSame($context['productQuantity'], $product->getQuantity());
    }
}
