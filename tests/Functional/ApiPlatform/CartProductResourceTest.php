<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class CartProductResourceTest extends ResourceTestUtils
{
    private const URI_KEY = '/api/cart_products';

    public function testAnonymousTokenACollectionContainsOnlyCartALines(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $document = $this->requestCollection($client);

        $this->assertCollectionContainsLine($document, $context['lineA']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineB']);
    }

    public function testAnonymousTokenBCollectionContainsOnlyCartBLines(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);

        $document = $this->requestCollection($client);

        $this->assertCollectionContainsLine($document, $context['lineB']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineA']);
    }

    public function testCollectionWithoutTokenIsEmpty(): void
    {
        $client = self::createClient();
        $this->createCartContext();

        $document = $this->requestCollection($client);

        self::assertSame([], $document['hydra:member']);
        self::assertSame(0, $document['hydra:totalItems']);
    }

    public function testMatchingTokenCanReadOwnItem(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $uri = $this->lineIri($context['lineA']);

        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertSame($uri, $document['@id']);
        self::assertSame($context['lineA']->getId(), $document['id']);
    }

    public function testWrongTokenCannotReadForeignItem(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);

        $client->request('GET', $this->lineIri($context['lineA']), [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMissingTokenCannotReadItem(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();

        $client->request('GET', $this->lineIri($context['lineA']), [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRegularUserRemainsTokenScoped(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $context['tokenA']);

        $document = $this->requestCollection($client);
        $this->assertCollectionContainsLine($document, $context['lineA']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineB']);

        $client->request('GET', $this->lineIri($context['lineB']), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminWithoutAdminContextRemainsTokenScoped(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $this->setCartToken($client, $context['tokenA']);

        $document = $this->requestCollection($client);
        $this->assertCollectionContainsLine($document, $context['lineA']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineB']);

        $client->request('GET', $this->lineIri($context['lineB']), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAdminWithAdminContextCanReadAllLinesAndItems(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $document = $this->requestCollection($client, '?context=admin');
        $this->assertCollectionContainsLine($document, $context['lineA']);
        $this->assertCollectionContainsLine($document, $context['lineB']);

        $uri = $this->lineIri($context['lineB']).'?context=admin';
        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame($context['lineB']->getId(), $this->getResponseDecodedContent($client)['id']);
    }

    public function testPredictableNumericIdDoesNotBypassCartIsolation(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);
        $foreignLineId = $context['lineA']->getId();
        self::assertNotNull($foreignLineId);

        $client->request('GET', self::URI_KEY.'/'.$foreignLineId, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMatchingCartTokenStillReturnsEmbeddedCartProducts(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $cartId = $context['cartA']->getId();
        self::assertNotNull($cartId);

        $client->request('GET', '/api/carts/'.$cartId, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertSame($cartId, $document['id']);
        self::assertIsArray($document['cartProducts']);
        self::assertContains($context['lineA']->getId(), array_column($document['cartProducts'], 'id'));
        self::assertNotContains($context['lineB']->getId(), array_column($document['cartProducts'], 'id'));
    }

    public function testMatchingTokenCanPatchOnlyQuantityOnOwnLine(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => 7]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertPersistedLineState(
            $context['lineA'],
            7,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    public function testWrongTokenCannotPatchForeignLine(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => 7]);

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    public function testMissingTokenCannotPatchExistingLine(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();

        $this->requestPatch($client, $context['lineA'], ['quantity' => 7]);

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    public function testMatchingTokenCannotPatchCart(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], [
            'cart' => $this->cartIri($context['cartB']),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    public function testMatchingTokenCannotPatchProduct(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], [
            'product' => $this->productIri($context['productB']),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    public function testCombinedReassignmentPayloadCannotBypassPatchOwnership(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], [
            'cart' => $this->cartIri($context['cartB']),
            'product' => $this->productIri($context['productB']),
            'quantity' => 7,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    #[DataProvider('validPostQuantities')]
    public function testMatchingTokenCanCreateLineWithValidQuantity(int $quantity): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
            'quantity' => $quantity,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($countBefore + 1, $this->countCartProducts());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $cartId = $context['cartA']->getId();
        $productId = $context['productB']->getId();
        self::assertNotNull($cartId);
        self::assertNotNull($productId);
        $entityManager->clear();
        $createdLine = $entityManager->getRepository(CartProduct::class)->findOneBy(
            [
                'cart' => $entityManager->getReference(Cart::class, $cartId),
                'product' => $entityManager->getReference(Product::class, $productId),
            ],
            ['id' => 'DESC']
        );

        self::assertInstanceOf(CartProduct::class, $createdLine);
        self::assertSame($quantity, $createdLine->getQuantity());
        self::assertSame($cartId, $createdLine->getCart()?->getId());
        self::assertSame($productId, $createdLine->getProduct()?->getId());
        $this->assertContextLinesUnchanged($context);
    }

    #[DataProvider('invalidPostSemanticQuantities')]
    public function testPostRejectsSemanticInvalidQuantity(mixed $quantity, string $message): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();
        $payload = [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
        ];
        if (null !== $quantity) {
            $payload['quantity'] = $quantity;
        }

        $this->requestPost($client, $payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationMessage($client, $message);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    #[DataProvider('invalidTypeQuantities')]
    public function testPostRejectsNonIntegerQuantityTypes(mixed $quantity): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
            'quantity' => $quantity,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    public function testPostRejectsMalformedJsonWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        $this->requestPostRaw($client, '{"quantity":');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    public function testPostRejectsUnknownWritableFieldWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
            'quantity' => 1,
            'unexpected' => true,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    #[DataProvider('validPatchQuantities')]
    public function testMatchingTokenCanPatchOwnLineWithValidQuantity(int $quantity): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => $quantity]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertPersistedLineState(
            $context['lineA'],
            $quantity,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    #[DataProvider('invalidPatchSemanticQuantities')]
    public function testPatchRejectsSemanticInvalidQuantity(mixed $quantity, string $message): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => $quantity]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationMessage($client, $message);
        $this->assertContextLinesUnchanged($context);
    }

    #[DataProvider('invalidTypeQuantities')]
    public function testPatchRejectsNonIntegerQuantityTypes(mixed $quantity): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => $quantity]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    public function testPatchRejectsMalformedJsonWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatchRaw($client, $context['lineA'], '{"quantity":');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    public function testPatchRejectsUnknownWritableFieldWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['unexpected' => true]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    public function testPatchWithoutQuantityKeepsCurrentState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], []);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertContextLinesUnchanged($context);
    }

    public function testCartProductOpenApiQuantitySchemaMatchesPolicy(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs.json', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        $postSchema = $this->requestSchema($document, 'post');
        $patchSchema = $this->requestSchema($document, 'patch');

        self::assertSame('integer', $postSchema['properties']['quantity']['type']);
        self::assertSame(1, $postSchema['properties']['quantity']['minimum']);
        self::assertContains('quantity', $postSchema['required']);
        self::assertFalse($postSchema['additionalProperties']);

        self::assertSame('integer', $patchSchema['properties']['quantity']['type']);
        self::assertSame(1, $patchSchema['properties']['quantity']['minimum']);
        self::assertArrayNotHasKey('maximum', $patchSchema['properties']['quantity']);
        self::assertFalse($patchSchema['additionalProperties']);
    }

    /** @return iterable<string, array{int}> */
    public static function validPostQuantities(): iterable
    {
        yield 'one' => [1];
        yield 'stock' => [20];
    }

    /** @return iterable<string, array{int|null, string}> */
    public static function invalidPostSemanticQuantities(): iterable
    {
        yield 'missing' => [null, 'Quantity is required.'];
        yield 'zero' => [0, 'Quantity must be at least 1.'];
        yield 'negative' => [-1, 'Quantity must be at least 1.'];
        yield 'overstock' => [21, 'Quantity cannot exceed the product stock.'];
    }

    /** @return iterable<string, array{int}> */
    public static function validPatchQuantities(): iterable
    {
        yield 'one' => [1];
        yield 'stock' => [10];
    }

    /** @return iterable<string, array{int, string}> */
    public static function invalidPatchSemanticQuantities(): iterable
    {
        yield 'zero' => [0, 'Quantity must be at least 1.'];
        yield 'negative' => [-1, 'Quantity must be at least 1.'];
        yield 'overstock' => [11, 'Quantity cannot exceed the product stock.'];
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidTypeQuantities(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['2'];
        yield 'decimal' => [1.5];
        yield 'boolean' => [true];
        yield 'array' => [[]];
        yield 'object' => [new \stdClass()];
    }

    /**
     * @return array{cartA: Cart, cartB: Cart, lineA: CartProduct, tokenA: string, productA: Product, lineB: CartProduct, tokenB: string, productB: Product}
     */
    private function createCartContext(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $tokenA = 'cart-a-'.$suffix;
        $tokenB = 'cart-b-'.$suffix;
        $productA = (new Product())
            ->setTitle('Cart product A '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(10);
        $productB = (new Product())
            ->setTitle('Cart product B '.$suffix)
            ->setPrice('20.00')
            ->setQuantity(20);
        $cartA = (new Cart())->setToken($tokenA);
        $cartB = (new Cart())->setToken($tokenB);
        $lineA = (new CartProduct())->setProduct($productA)->setQuantity(1);
        $lineB = (new CartProduct())->setProduct($productB)->setQuantity(2);
        $cartA->addCartProduct($lineA);
        $cartB->addCartProduct($lineB);

        foreach ([$productA, $productB, $cartA, $cartB] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        self::assertNotNull($cartA->getId());
        self::assertNotNull($lineA->getId());
        self::assertNotNull($lineB->getId());

        return [
            'cartA' => $cartA,
            'cartB' => $cartB,
            'lineA' => $lineA,
            'tokenA' => $tokenA,
            'productA' => $productA,
            'lineB' => $lineB,
            'tokenB' => $tokenB,
            'productB' => $productB,
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function requestCollection(AbstractBrowser $client, string $query = ''): array
    {
        $client->request('GET', self::URI_KEY.$query, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        return $this->getResponseDecodedContent($client);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertCollectionContainsLine(array $document, CartProduct $line): void
    {
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        self::assertContains($this->lineIri($line), array_column($document['hydra:member'], '@id'));
        self::assertContains($lineId, array_column($document['hydra:member'], 'id'));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertCollectionDoesNotContainLine(array $document, CartProduct $line): void
    {
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        self::assertNotContains($this->lineIri($line), array_column($document['hydra:member'], '@id'));
        self::assertNotContains($lineId, array_column($document['hydra:member'], 'id'));
    }

    private function lineIri(CartProduct $line): string
    {
        $lineId = $line->getId();
        self::assertNotNull($lineId);

        return self::URI_KEY.'/'.$lineId;
    }

    private function cartIri(Cart $cart): string
    {
        $cartId = $cart->getId();
        self::assertNotNull($cartId);

        return '/api/carts/'.$cartId;
    }

    private function productIri(Product $product): string
    {
        return '/api/products/'.$product->getUuid();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requestPatch(AbstractBrowser $client, CartProduct $line, array $payload): void
    {
        $client->request(
            'PATCH',
            $this->lineIri($line),
            [],
            [],
            self::REQUEST_HEADERS_PATCH,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /** @param array<string, mixed> $payload */
    private function requestPost(AbstractBrowser $client, array $payload): void
    {
        $client->request(
            'POST',
            self::URI_KEY,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    private function requestPostRaw(AbstractBrowser $client, string $content): void
    {
        $client->request('POST', self::URI_KEY, [], [], self::REQUEST_HEADERS, $content);
    }

    private function requestPatchRaw(AbstractBrowser $client, CartProduct $line, string $content): void
    {
        $client->request(
            'PATCH',
            $this->lineIri($line),
            [],
            [],
            self::REQUEST_HEADERS_PATCH,
            $content
        );
    }

    private function countCartProducts(): int
    {
        return self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(CartProduct::class)
            ->count([]);
    }

    /** @param array<string, mixed> $context */
    private function assertContextLinesUnchanged(array $context): void
    {
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
        $this->assertPersistedLineState(
            $context['lineB'],
            2,
            $context['cartB']->getId(),
            $context['productB']->getId()
        );
    }

    private function assertValidationMessage(AbstractBrowser $client, string $message): void
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString($message, $content);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function requestSchema(array $document, string $method): array
    {
        $contentType = 'patch' === $method ? 'application/merge-patch+json' : 'application/json';
        $path = 'patch' === $method ? self::URI_KEY.'/{id}' : self::URI_KEY;
        $reference = $document['paths'][$path][$method]['requestBody']['content'][$contentType]['schema'];
        self::assertIsArray($reference);
        self::assertArrayHasKey('$ref', $reference);
        self::assertIsString($reference['$ref']);
        $schemaName = substr($reference['$ref'], strlen('#/components/schemas/'));
        self::assertNotSame($reference['$ref'], $schemaName);
        $schema = $document['components']['schemas'][$schemaName];
        self::assertIsArray($schema);

        return $schema;
    }

    private function assertPersistedLineState(CartProduct $line, int $quantity, ?int $cartId, ?int $productId): void
    {
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        self::assertNotNull($cartId);
        self::assertNotNull($productId);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $persistedLine = $entityManager->find(CartProduct::class, $lineId);

        self::assertInstanceOf(CartProduct::class, $persistedLine);
        self::assertSame($quantity, $persistedLine->getQuantity());
        self::assertSame($cartId, $persistedLine->getCart()?->getId());
        self::assertSame($productId, $persistedLine->getProduct()?->getId());
    }
}
