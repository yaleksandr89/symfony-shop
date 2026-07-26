<?php

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class ProductResourceTest extends ResourceTestUtils
{
    /** @var string */
    protected string $uriKey = '/api/products';

    private const PRIVATE_FIELDS = [
        'createdAt',
        'description',
        'isPublished',
        'isDeleted',
        'productImages',
        'slug',
        'cartProducts',
        'orderProducts',
    ];

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
        self::assertSame('hydra:Collection', $document['@type']);
        self::assertIsArray($document['hydra:member']);
        self::assertCount(2, $document['hydra:member']);
        self::assertIsInt($document['hydra:totalItems']);
        self::assertGreaterThan(2, $document['hydra:totalItems']);
        self::assertIsArray($document['hydra:view']);
        self::assertSame('hydra:PartialCollectionView', $document['hydra:view']['@type']);
        self::assertArrayHasKey('hydra:first', $document['hydra:view']);
        self::assertArrayHasKey('hydra:last', $document['hydra:view']);

        $expectedIri = $this->uriKey.'/'.$product->getUuid();
        $member = $this->findMemberByIri($document['hydra:member'], $expectedIri);

        self::assertSame('Product', $member['@type']);
        self::assertSame($product->getId(), $member['id']);
        self::assertSame((string) $product->getUuid(), $member['uuid']);
        self::assertSame($product->getTitle(), $member['title']);
        self::assertSame($product->getPrice(), $member['price']);
        self::assertSame($product->getQuantity(), $member['quantity']);
        self::assertSame($product->getCategory()?->getId(), $member['category']['id']);
        self::assertSame($product->getCategory()?->getTitle(), $member['category']['title']);
        $this->assertPrivateFieldsAreAbsent($member);
    }

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
        self::assertSame($product->getCategory()?->getId(), $document['category']['id']);
        self::assertSame($product->getCategory()?->getTitle(), $document['category']['title']);
        self::assertArrayNotHasKey('id', $document);
        $this->assertPrivateFieldsAreAbsent($document);
    }

    public function testFilterProductsByPublishedState(): void
    {
        $client = self::createClient();

        $client->request('GET', $this->uriKey.'?itemsPerPage=100', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $unfilteredDocument = $this->getResponseDecodedContent($client);

        $client->request('GET', $this->uriKey.'?isPublished=false', [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $filteredDocument = $this->getResponseDecodedContent($client);

        self::assertSame('/api/contexts/Product', $filteredDocument['@context']);
        self::assertSame('hydra:Collection', $filteredDocument['@type']);
        self::assertIsArray($filteredDocument['hydra:member']);
        self::assertSame([], $filteredDocument['hydra:member']);
        self::assertSame(0, $filteredDocument['hydra:totalItems']);
        self::assertGreaterThan(
            $filteredDocument['hydra:totalItems'],
            $unfilteredDocument['hydra:totalItems']
        );
    }

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
        self::assertResponseHeaderSame('location', $document['@id']);
        self::assertSame(
            $productCount + 1,
            self::getContainer()->get(ProductRepository::class)->count([])
        );
    }

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

    public function testPathProductWithAccess(): void
    {
        $client = self::createClient();
        $product = $this->getStableProduct();
        $user = $this->getUser(UserFixtures::USER_ADMIN_1_EMAIL);

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

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $document = $this->getResponseDecodedContent($client);
        self::assertSame($uri, $document['@id']);
        self::assertSame('Product', $document['@type']);
        self::assertSame($context['title'], $document['title']);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $updatedProduct = self::getContainer()->get(ProductRepository::class)->findOneBy([
            'uuid' => (string) $product->getUuid(),
        ]);
        self::assertInstanceOf(Product::class, $updatedProduct);
        self::assertSame($context['title'], $updatedProduct->getTitle());
    }

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
