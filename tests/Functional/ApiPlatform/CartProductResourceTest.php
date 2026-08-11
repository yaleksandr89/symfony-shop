<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Utils\Generator\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class CartProductResourceTest extends ResourceTestUtils
{
    private const URI_KEY = '/api/cart_products';

    #[TestDox('Коллекция позиций доступна только владельцу корзины с токеном A')]
    public function testAnonymousTokenACollectionContainsOnlyCartALines(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $document = $this->requestCollection($client);

        $this->assertCollectionContainsLine($document, $context['lineA']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineB']);
    }

    #[TestDox('Коллекция позиций доступна только владельцу корзины с токеном B')]
    public function testAnonymousTokenBCollectionContainsOnlyCartBLines(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);

        $document = $this->requestCollection($client);

        $this->assertCollectionContainsLine($document, $context['lineB']);
        $this->assertCollectionDoesNotContainLine($document, $context['lineA']);
    }

    #[TestDox('Без токена коллекция позиций корзины пуста')]
    public function testCollectionWithoutTokenIsEmpty(): void
    {
        $client = self::createClient();
        $this->createCartContext();

        $document = $this->requestCollection($client);

        self::assertSame([], $document['member']);
        self::assertSame(0, $document['totalItems']);
    }

    #[TestDox('Владелец по токену может получить свою позицию корзины')]
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

    #[TestDox('Чужой токен не даёт прочитать позицию корзины')]
    public function testWrongTokenCannotReadForeignItem(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);

        $client->request('GET', $this->lineIri($context['lineA']), [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('Без токена нельзя прочитать позицию корзины')]
    public function testMissingTokenCannotReadItem(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();

        $client->request('GET', $this->lineIri($context['lineA']), [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('Доступ обычного пользователя ограничен токеном корзины')]
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

    #[TestDox('Администратор вне административного контекста ограничен токеном корзины')]
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

    #[TestDox('Администратор в административном контексте видит все позиции корзин')]
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

    #[TestDox('Токен корзины возвращает вложенные позиции этой корзины')]
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

    #[TestDox('Чужой токен не позволяет изменить позицию корзины')]
    public function testWrongTokenCannotPatchForeignLine(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $this->setCartToken($client, $context['tokenB']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => 7]);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    #[TestDox('Без токена нельзя изменить существующую позицию корзины')]
    public function testMissingTokenCannotPatchExistingLine(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();

        $this->requestPatch($client, $context['lineA'], ['quantity' => 7]);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        $this->assertPersistedLineState(
            $context['lineA'],
            1,
            $context['cartA']->getId(),
            $context['productA']->getId()
        );
    }

    #[TestDox('Токен позиции не даёт изменить саму корзину')]
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

    #[TestDox('Токен позиции не даёт изменить товар')]
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

    #[TestDox('Подмена связей не обходит проверку владения при PATCH')]
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
    #[TestDox('Владелец по токену создаёт позицию с допустимым количеством')]
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

    #[DataProvider('hiddenProducts')]
    #[TestDox('Скрытый товар нельзя добавить в корзину по IRI')]
    public function testHiddenProductIriCannotCreateCartLine(bool $isPublished, bool $isDeleted): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $hiddenProduct = $this->createProduct('Hidden cart product', $isPublished, $isDeleted);
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($hiddenProduct),
            'quantity' => 1,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertStringNotContainsString($hiddenProduct->getTitle() ?? '', (string) $client->getResponse()->getContent());
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Повторное добавление того же товара возвращает конфликт без изменения состояния')]
    public function testFirstThenDuplicateCartProductReturnsConflictWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
            'quantity' => 3,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $countAfterFirstPost = $this->countCartProducts();
        $createdLine = $this->findCartProduct($context['cartA'], $context['productB']);
        $createdLineId = $createdLine->getId();
        self::assertNotNull($createdLineId);

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productB']),
            'quantity' => 7,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame($countAfterFirstPost, $this->countCartProducts());

        $persistedLine = $this->findCartProduct($context['cartA'], $context['productB']);
        self::assertSame($createdLineId, $persistedLine->getId());
        self::assertSame(3, $persistedLine->getQuantity());
        self::assertSame($context['cartA']->getId(), $persistedLine->getCart()?->getId());
        self::assertSame($context['productB']->getId(), $persistedLine->getProduct()?->getId());
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('В одну корзину можно добавить разные товары')]
    public function testSameCartCanCreateLinesForDifferentProducts(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $productC = $this->createProduct('Cart product C');
        $this->setCartToken($client, $context['tokenA']);
        $countBefore = $this->countCartProducts();

        foreach ([$context['productB'], $productC] as $product) {
            $this->requestPost($client, [
                'cart' => $this->cartIri($context['cartA']),
                'product' => $this->productIri($product),
                'quantity' => 1,
            ]);
            self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        }

        self::assertSame($countBefore + 2, $this->countCartProducts());
        self::assertSame(1, $this->findCartProduct($context['cartA'], $context['productB'])->getQuantity());
        self::assertSame(1, $this->findCartProduct($context['cartA'], $productC)->getQuantity());
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Один товар можно добавить в разные корзины')]
    public function testSameProductCanCreateLinesForDifferentCarts(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $productC = $this->createProduct('Cart product C');
        $countBefore = $this->countCartProducts();

        $this->setCartToken($client, $context['tokenA']);
        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($productC),
            'quantity' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $client->getCookieJar()->clear();
        $this->setCartToken($client, $context['tokenB']);
        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartB']),
            'product' => $this->productIri($productC),
            'quantity' => 1,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        self::assertSame($countBefore + 2, $this->countCartProducts());
        self::assertSame(1, $this->findCartProduct($context['cartA'], $productC)->getQuantity());
        self::assertSame(1, $this->findCartProduct($context['cartB'], $productC)->getQuantity());
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Чужой токен не превращает существующую пару в ответ о конфликте')]
    public function testWrongTokenCannotTurnAnExistingPairIntoAConflictResponse(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenB']);
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productA']),
            'quantity' => 7,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Отсутствующий токен не превращает существующую пару в ответ о конфликте')]
    public function testMissingTokenCannotTurnAnExistingPairIntoAConflictResponse(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $countBefore = $this->countCartProducts();

        $this->requestPost($client, [
            'cart' => $this->cartIri($context['cartA']),
            'product' => $this->productIri($context['productA']),
            'quantity' => 7,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($countBefore, $this->countCartProducts());
        $this->assertContextLinesUnchanged($context);
    }

    #[DataProvider('invalidPostSemanticQuantities')]
    #[TestDox('POST отклоняет недопустимое количество товара')]
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
    #[TestDox('POST отклоняет количество нецелого типа')]
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

    #[TestDox('Некорректный JSON в POST не меняет состояние')]
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

    #[TestDox('Неизвестное доступное для записи поле в POST не меняет состояние')]
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
    #[TestDox('Владелец по токену изменяет свою позицию с допустимым количеством')]
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
    #[TestDox('PATCH отклоняет недопустимое количество товара')]
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
    #[TestDox('PATCH отклоняет количество нецелого типа')]
    public function testPatchRejectsNonIntegerQuantityTypes(mixed $quantity): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['quantity' => $quantity]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Некорректный JSON в PATCH не меняет состояние')]
    public function testPatchRejectsMalformedJsonWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatchRaw($client, $context['lineA'], '{"quantity":');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Неизвестное доступное для записи поле в PATCH не меняет состояние')]
    public function testPatchRejectsUnknownWritableFieldWithoutChangingState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], ['unexpected' => true]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('PATCH без количества сохраняет прежнее состояние позиции')]
    public function testPatchWithoutQuantityKeepsCurrentState(): void
    {
        $client = self::createClient();
        $context = $this->createCartContext();
        $this->setCartToken($client, $context['tokenA']);

        $this->requestPatch($client, $context['lineA'], []);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertContextLinesUnchanged($context);
    }

    #[TestDox('Схема OpenAPI для количества соответствует правилам корзины')]
    public function testCartProductOpenApiQuantitySchemaMatchesPolicy(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/docs.jsonopenapi', [], [], ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

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

    /** @return iterable<string, array{bool, bool}> */
    public static function hiddenProducts(): iterable
    {
        yield 'unpublished' => [false, false];
        yield 'deleted' => [true, true];
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
        $tokenA = TokenGenerator::generateToken();
        $tokenB = TokenGenerator::generateToken();
        $productA = (new Product())
            ->setTitle('Cart product A '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(10)
            ->setIsPublished(true);
        $productB = (new Product())
            ->setTitle('Cart product B '.$suffix)
            ->setPrice('20.00')
            ->setQuantity(20)
            ->setIsPublished(true);
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
        self::assertContains($this->lineIri($line), array_column($document['member'], '@id'));
        self::assertContains($lineId, array_column($document['member'], 'id'));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertCollectionDoesNotContainLine(array $document, CartProduct $line): void
    {
        $lineId = $line->getId();
        self::assertNotNull($lineId);
        self::assertNotContains($this->lineIri($line), array_column($document['member'], '@id'));
        self::assertNotContains($lineId, array_column($document['member'], 'id'));
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

    private function createProduct(string $prefix, bool $isPublished = true, bool $isDeleted = false): Product
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $product = (new Product())
            ->setTitle($prefix.' '.$suffix)
            ->setPrice('30.00')
            ->setQuantity(10)
            ->setIsPublished($isPublished)
            ->setIsDeleted($isDeleted);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function findCartProduct(Cart $cart, Product $product): CartProduct
    {
        $cartId = $cart->getId();
        $productId = $product->getId();
        self::assertNotNull($cartId);
        self::assertNotNull($productId);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $line = $entityManager->getRepository(CartProduct::class)->findOneBy([
            'cart' => $entityManager->getReference(Cart::class, $cartId),
            'product' => $entityManager->getReference(Product::class, $productId),
        ]);
        self::assertInstanceOf(CartProduct::class, $line);

        return $line;
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
