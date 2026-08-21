<?php

namespace App\Tests\Functional\Catalog\ApiPlatform;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use App\Catalog\Repository\ProductRepository;
use App\Account\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
class ProductResourceTest extends \App\Tests\Functional\ApiPlatform\ResourceTestUtils
{
    /** @var string */
    protected string $uriKey = '/api/products';

    private const PRIVATE_FIELDS = [
        'createdAt',
        'updatedAt',
        'description',
        'isPublished',
        'isDeleted',
        'productImages',
        'slug',
        'cartProducts',
        'orderProducts',
    ];

    #[TestDox('Коллекция товаров возвращает публичные поля и пагинацию')]
    public function testGetProducts(): void
    {
        $client = self::createClient();
        $product = $this->getStableProduct('DESC');

        $client->request('GET', $this->uriKey.'?itemsPerPage=2', [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $contentType = $client->getResponse()->headers->get('content-type');
        self::assertIsString($contentType);
        self::assertStringStartsWith('application/ld+json', $contentType);

        $document = $this->getResponseDecodedContent($client);
        self::assertSame('/api/contexts/Product', $document['@context']);
        self::assertSame('Collection', $document['@type']);
        self::assertIsArray($document['member']);
        self::assertCount(2, $document['member']);
        self::assertIsInt($document['totalItems']);
        self::assertGreaterThan(2, $document['totalItems']);
        self::assertIsArray($document['view']);
        self::assertSame('PartialCollectionView', $document['view']['@type']);
        self::assertArrayHasKey('first', $document['view']);
        self::assertArrayHasKey('last', $document['view']);

        $expectedIri = $this->uriKey.'/'.$product->getUuid();
        $member = $this->findMemberByIri($document['member'], $expectedIri);

        self::assertSame('Product', $member['@type']);
        self::assertSame($product->getId(), $member['id']);
        self::assertSame((string) $product->getUuid(), $member['uuid']);
        self::assertSame($product->getTitle(), $member['title']);
        self::assertSame($product->getPrice(), $member['price']);
        self::assertSame($product->getQuantity(), $member['quantity']);
        self::assertSame($product->getIsNew(), $member['isNew']);
        self::assertSame($product->getIsOnSale(), $member['isOnSale']);
        self::assertSame($product->getCategory()?->getId(), $member['category']['id']);
        self::assertSame($product->getCategory()?->getTitle(), $member['category']['title']);
        $this->assertPrivateFieldsAreAbsent($member);
    }

    #[TestDox('Карточка товара возвращает публичные поля без внутреннего идентификатора')]
    public function testGetProduct(): void
    {
        $client = self::createClient();
        $product = $this->getStableProduct();

        $uri = $this->uriKey.'/'.$product->getUuid();

        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $document = $this->getResponseDecodedContent($client);
        self::assertSame('/api/contexts/Product', $document['@context']);
        self::assertSame($uri, $document['@id']);
        self::assertSame('Product', $document['@type']);
        self::assertSame((string) $product->getUuid(), $document['uuid']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $document['uuid']
        );
        self::assertStringEndsWith('/'.$document['uuid'], $document['@id']);
        self::assertSame($product->getTitle(), $document['title']);
        self::assertSame($product->getPrice(), $document['price']);
        self::assertSame($product->getQuantity(), $document['quantity']);
        self::assertSame($product->getIsNew(), $document['isNew']);
        self::assertSame($product->getIsOnSale(), $document['isOnSale']);
        self::assertSame($product->getCategory()?->getId(), $document['category']['id']);
        self::assertSame($product->getCategory()?->getTitle(), $document['category']['title']);
        self::assertArrayNotHasKey('id', $document);
        $this->assertPrivateFieldsAreAbsent($document);
    }

    #[TestDox('Анонимный пользователь видит только опубликованные и не удалённые товары')]
    public function testAnonymousProductVisibility(): void
    {
        $client = self::createClient();
        $published = $this->createProduct('Published product', true);
        $unpublished = $this->createProduct('Unpublished product');
        $deleted = $this->createProduct('Deleted product', true, true);

        $client->request('GET', $this->uriKey.'?itemsPerPage=100', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $iris = array_column($this->getResponseDecodedContent($client)['member'], '@id');
        self::assertContains($this->uriKey.'/'.$published->getUuid(), $iris);
        self::assertNotContains($this->uriKey.'/'.$unpublished->getUuid(), $iris);
        self::assertNotContains($this->uriKey.'/'.$deleted->getUuid(), $iris);

        $client->request('GET', $this->uriKey.'/'.$published->getUuid(), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        foreach ([$unpublished, $deleted] as $product) {
            $client->request('GET', $this->uriKey.'/'.$product->getUuid(), [], [], self::REQUEST_HEADERS);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $client->request('GET', $this->uriKey.'?isPublished=false', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->getResponseDecodedContent($client)['member']);
    }

    #[TestDox('Обычный пользователь видит только опубликованные и не удалённые товары')]
    public function testRegularUserProductVisibility(): void
    {
        $client = self::createClient();
        $published = $this->createProduct('User published product', true);
        $unpublished = $this->createProduct('User unpublished product');
        $deleted = $this->createProduct('User deleted product', true, true);
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $client->request('GET', $this->uriKey.'/'.$published->getUuid(), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        foreach ([$unpublished, $deleted] as $product) {
            $client->request('GET', $this->uriKey.'/'.$product->getUuid(), [], [], self::REQUEST_HEADERS);
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        $client->request('GET', $this->uriKey.'?isPublished=false', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->getResponseDecodedContent($client)['member']);
    }

    #[TestDox('Администратор читает и изменяет черновик, но не удалённый товар')]
    public function testAdminCanReadAndPatchUnpublishedProductButNotDeletedProduct(): void
    {
        $client = self::createClient();
        $unpublished = $this->createProduct('Admin unpublished product');
        $deleted = $this->createProduct('Admin deleted product', true, true);
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $client->request('GET', $this->uriKey.'?isPublished=false', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertContains(
            $this->uriKey.'/'.$unpublished->getUuid(),
            array_column($this->getResponseDecodedContent($client)['member'], '@id')
        );

        $uri = $this->uriKey.'/'.$unpublished->getUuid();
        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('PATCH', $uri, [], [], self::REQUEST_HEADERS_PATCH, json_encode(['title' => 'Updated admin draft'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertSame('Updated admin draft', $document['title']);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = self::getContainer()->get(ProductRepository::class)->findOneBy([
            'uuid' => (string) $unpublished->getUuid(),
        ]);
        self::assertInstanceOf(Product::class, $reloaded);
        self::assertSame('Updated admin draft', $reloaded->getTitle());

        $client->request('GET', $this->uriKey.'/'.$deleted->getUuid(), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('Администратор создаёт товар с переданными публичными полями')]
    public function testCreatedProductWithAccess(): void
    {
        $client = self::createClient();
        $user = $this->getUser(UserFixtures::USER_ADMIN_1_EMAIL);
        $client->loginUser($user, 'website');

        $productCount = self::getContainer()->get(ProductRepository::class)->count([]);

        $context = [
            'title' => 'New product',
            'price' => '100',
            'quantity' => 5,
            'isNew' => true,
            'isOnSale' => false,
        ];

        $client->request(
            'POST',
            $this->uriKey,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($context, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $document = $this->getResponseDecodedContent($client);
        self::assertSame('Product', $document['@type']);
        self::assertStringStartsWith($this->uriKey.'/', $document['@id']);
        self::assertSame($context['title'], $document['title']);
        self::assertSame($context['price'], $document['price']);
        self::assertSame(5, $document['quantity']);
        self::assertTrue($document['isNew']);
        self::assertFalse($document['isOnSale']);
        self::assertResponseHeaderSame('location', $document['@id']);
        self::assertSame(
            $productCount + 1,
            self::getContainer()->get(ProductRepository::class)->count([])
        );

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $created = self::getContainer()->get(ProductRepository::class)->findOneBy([
            'uuid' => basename($document['@id']),
        ]);
        self::assertInstanceOf(Product::class, $created);
        self::assertSame($context['title'], $created->getTitle());
        self::assertSame($context['price'], $created->getPrice());
        self::assertSame(5, $created->getQuantity());
        self::assertTrue($created->getIsNew());
        self::assertFalse($created->getIsOnSale());
        self::assertFalse($created->getIsPublished());
        self::assertFalse($created->getIsDeleted());
    }

    #[TestDox('Обычный пользователь не создаёт товар')]
    public function testCreatedProductWithoutAccess(): void
    {
        $client = self::createClient();
        $user = $this->getUser(UserFixtures::USER_1_EMAIL);
        $client->loginUser($user, 'website');

        $productCount = self::getContainer()->get(ProductRepository::class)->count([]);

        $context = [
            'title' => 'Forbidden product',
            'price' => '100',
            'quantity' => 5,
        ];

        $client->request(
            'POST',
            $this->uriKey,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($context, JSON_THROW_ON_ERROR)
        );

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        self::assertSame(
            $productCount,
            self::getContainer()->get(ProductRepository::class)->count([])
        );
    }

    #[TestDox('Анонимный пользователь не создаёт товар')]
    public function testAnonymousUserCannotCreateProduct(): void
    {
        $client = self::createClient();
        $productCount = self::getContainer()->get(ProductRepository::class)->count([]);

        $context = [
            'title' => 'Anonymous product',
            'price' => '100',
            'quantity' => 5,
        ];

        $client->request(
            'POST',
            $this->uriKey,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($context, JSON_THROW_ON_ERROR)
        );

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        self::assertSame(
            $productCount,
            self::getContainer()->get(ProductRepository::class)->count([])
        );
    }

    #[TestDox('Администратор изменяет разрешённые поля товара, не подменяя системную дату')]
    public function testPathProductWithAccess(): void
    {
        $client = self::createClient();
        $product = $this->getStableProduct();
        $user = $this->getUser(UserFixtures::USER_ADMIN_1_EMAIL);

        $client->loginUser($user, 'website');

        $uri = $this->uriKey.'/'.$product->getUuid();
        $context = [
            'title' => 'Update product',
            'isNew' => true,
            'isOnSale' => true,
            'updatedAt' => '2000-01-01T00:00:00+00:00',
        ];

        $client->request(
            'PATCH',
            $uri,
            [],
            [],
            self::REQUEST_HEADERS_PATCH,
            json_encode($context, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $document = $this->getResponseDecodedContent($client);
        self::assertSame($uri, $document['@id']);
        self::assertSame('Product', $document['@type']);
        self::assertSame($context['title'], $document['title']);
        self::assertTrue($document['isNew']);
        self::assertTrue($document['isOnSale']);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $updatedProduct = self::getContainer()->get(ProductRepository::class)->findOneBy([
            'uuid' => (string) $product->getUuid(),
        ]);
        self::assertInstanceOf(Product::class, $updatedProduct);
        self::assertSame($context['title'], $updatedProduct->getTitle());
        self::assertTrue($updatedProduct->getIsNew());
        self::assertTrue($updatedProduct->getIsOnSale());
        self::assertNotSame($context['updatedAt'], $updatedProduct->getUpdatedAt()->format(DATE_ATOM));
    }

    #[TestDox('Обычный пользователь не изменяет товар')]
    public function testPathProductWithoutAccess(): void
    {
        $client = self::createClient();
        $product = $this->getStableProduct();
        $originalTitle = $product->getTitle();
        $uuid = (string) $product->getUuid();
        $user = $this->getUser(UserFixtures::USER_1_EMAIL);

        $client->loginUser($user, 'website');

        $uri = $this->uriKey.'/'.$product->getUuid();
        $context = [
            'title' => 'Update product',
        ];

        $client->request(
            'PATCH',
            $uri,
            [],
            [],
            self::REQUEST_HEADERS_PATCH,
            json_encode($context, JSON_THROW_ON_ERROR)
        );

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $unchangedProduct = self::getContainer()->get(ProductRepository::class)->findOneBy(['uuid' => $uuid]);
        self::assertInstanceOf(Product::class, $unchangedProduct);
        self::assertSame($originalTitle, $unchangedProduct->getTitle());
    }

    #[TestDox('Фильтр товаров принимает числовой идентификатор категории без отдельного маршрута')]
    public function testProductCategorySearchFilterAcceptsNumericIdWithoutCategoryItemRoute(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertNull($router->getRouteCollection()->get('api_categories_get_item'));
        $suffix = str_replace('.', '', uniqid('', true));
        $selectedCategory = (new Category())->setTitle('Filtered category '.$suffix);
        $otherCategory = (new Category())->setTitle('Other category '.$suffix);
        $selectedProduct = (new Product())
            ->setTitle('Selected product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($selectedCategory);
        $otherProduct = (new Product())
            ->setTitle('Other product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($otherCategory);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$selectedCategory, $otherCategory, $selectedProduct, $otherProduct] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $selectedCategoryId = $selectedCategory->getId();
        self::assertIsInt($selectedCategoryId);
        $selectedProductIri = $this->uriKey.'/'.$selectedProduct->getUuid();
        $otherProductIri = $this->uriKey.'/'.$otherProduct->getUuid();

        $client->request('GET', $this->uriKey.'?category='.$selectedCategoryId.'&isPublished=true&itemsPerPage=100', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $numericDocument = $this->getResponseDecodedContent($client);
        $numericIris = array_column($numericDocument['member'], '@id');
        self::assertContains($selectedProductIri, $numericIris);
        self::assertNotContains($otherProductIri, $numericIris);

        $numericProduct = $this->findMemberByIri($numericDocument['member'], $selectedProductIri);
        self::assertSame($selectedCategoryId, $numericProduct['category']['id']);
        self::assertSame($selectedCategory->getTitle(), $numericProduct['category']['title']);
        $this->assertPrivateFieldsAreAbsent($numericProduct);
    }

    private function getStableProduct(string $order = 'ASC'): Product
    {
        $product = self::getContainer()->get(ProductRepository::class)->findOneBy([], ['id' => $order]);

        self::assertInstanceOf(Product::class, $product);

        return $product;
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createProduct(string $title, bool $isPublished = false, bool $isDeleted = false): Product
    {
        $product = (new Product())
            ->setTitle($title.' '.uniqid('', true))
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished($isPublished)
            ->setIsDeleted($isDeleted);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    /**
     * @param array<int, array<string, mixed>> $members
     *
     * @return array<string, mixed>
     */
    private function findMemberByIri(array $members, string $iri): array
    {
        foreach ($members as $member) {
            if (($member['@id'] ?? null) === $iri) {
                return $member;
            }
        }

        self::fail(sprintf('Product with IRI "%s" was not found in the collection.', $iri));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function assertPrivateFieldsAreAbsent(array $document): void
    {
        foreach (self::PRIVATE_FIELDS as $field) {
            self::assertArrayNotHasKey($field, $document);
        }
    }
}
