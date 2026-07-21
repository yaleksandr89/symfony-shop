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

    public function testPostStillAcceptsCartProductAndQuantityForMatchingToken(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);
        $quantity = 7;

        $client->request(
            'POST',
            self::URI_KEY,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode([
                'cart' => $this->cartIri($context['cartA']),
                'product' => $this->productIri($context['productA']),
                'quantity' => $quantity,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $cartId = $context['cartA']->getId();
        $productId = $context['productA']->getId();
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
     * @param array<string, int|string> $payload
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
