<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
class CategoryResourceTest extends ResourceTestUtils
{
    private const COLLECTION_URI = '/api/categories';

    #[TestDox('Маршрут коллекции и OpenAPI не раскрывают чтение отдельной категории')]
    public function testCollectionRouteAndOpenApiContractExcludeItemRead(): void
    {
        $client = self::createClient();
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();

        $collectionRoute = $routes->get('api_categories_get_collection');
        self::assertNotNull($collectionRoute);
        self::assertSame('/api/categories.{_format}', $collectionRoute->getPath());
        self::assertSame(['GET'], $collectionRoute->getMethods());
        self::assertNull($routes->get('api_categories_get_item'));

        $client->request('GET', '/api/docs.jsonopenapi', [], [], ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertArrayHasKey(self::COLLECTION_URI, $document['paths']);
        self::assertSame(['get'], array_keys($document['paths'][self::COLLECTION_URI]));
        self::assertArrayNotHasKey(self::COLLECTION_URI.'/{id}', $document['paths']);
    }

    #[TestDox('Коллекция категорий доступна только администратору')]
    public function testCategoryCollectionRequiresAdminRole(): void
    {
        $client = self::createClient();

        $client->request('GET', self::COLLECTION_URI, [], [], self::REQUEST_HEADERS);
        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);

        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $client->request('GET', self::COLLECTION_URI, [], [], self::REQUEST_HEADERS);
        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
    }

    #[TestDox('Администраторы видят только активные категории в минимальном представлении')]
    public function testUnverifiedAndVerifiedAdminsCanReadOnlyActiveMinimalCategories(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $activeCategory = (new Category())->setTitle('Active category '.$suffix);
        $deletedCategory = (new Category())
            ->setTitle('Deleted category '.$suffix)
            ->setIsDeleted(true);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($activeCategory);
        $entityManager->persist($deletedCategory);
        $entityManager->flush();

        $client->loginUser($this->createUnverifiedAdmin(), 'website');
        $client->request('GET', self::COLLECTION_URI, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $client->request('GET', self::COLLECTION_URI, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertSame('/api/contexts/Category', $document['@context']);
        self::assertSame('Collection', $document['@type']);
        self::assertIsArray($document['member']);

        $activeCategoryId = $activeCategory->getId();
        $deletedCategoryId = $deletedCategory->getId();
        self::assertIsInt($activeCategoryId);
        self::assertIsInt($deletedCategoryId);
        $membersById = [];
        foreach ($document['member'] as $member) {
            self::assertIsArray($member);
            $membersById[$member['id']] = $member;
        }
        self::assertArrayHasKey($activeCategoryId, $membersById);
        self::assertArrayNotHasKey($deletedCategoryId, $membersById);

        $activeMember = $membersById[$activeCategoryId];
        self::assertSame(['@id', '@type', 'id', 'title'], array_keys($activeMember));
        self::assertSame('Category', $activeMember['@type']);
        self::assertSame($activeCategoryId, $activeMember['id']);
        self::assertSame($activeCategory->getTitle(), $activeMember['title']);
        foreach (['slug', 'products', 'isDeleted'] as $privateField) {
            self::assertArrayNotHasKey($privateField, $activeMember);
        }

        $client->request('GET', self::COLLECTION_URI.'/'.$activeCategoryId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[TestDox('Отсутствие item-маршрута не меняет вложенное представление категорий')]
    public function testAbsentCategoryItemRouteKeepsProductAndOrderCategoryEmbedsShallow(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())->setTitle('Embedded category '.$suffix);
        $product = (new Product())
            ->setTitle('Embedded product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($category);
        $order = (new Order())
            ->setOwner($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL))
            ->setStatus(1)
            ->setTotalPrice('10.00');
        $order->addOrderProduct((new OrderProduct())
            ->setProduct($product)
            ->setQuantity(1)
            ->setPricePerOne('10.00'));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        foreach ([$category, $product, $order] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $categoryId = $category->getId();
        $orderId = $order->getId();
        self::assertIsInt($categoryId);
        self::assertIsInt($orderId);

        $client->request('GET', '/api/products/'.$product->getUuid(), [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $productDocument = $this->getResponseDecodedContent($client);
        self::assertSame(['@id', '@type', 'id', 'title'], array_keys($productDocument['category']));
        self::assertSame($categoryId, $productDocument['category']['id']);
        self::assertSame($category->getTitle(), $productDocument['category']['title']);

        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $client->request('GET', '/api/orders/'.$orderId, [], [], self::REQUEST_HEADERS);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $orderDocument = $this->getResponseDecodedContent($client);
        self::assertCount(1, $orderDocument['orderProducts']);
        $orderCategory = $orderDocument['orderProducts'][0]['product']['category'];
        self::assertSame(['@id', '@type', 'id', 'title'], array_keys($orderCategory));
        self::assertSame($categoryId, $orderCategory['id']);
        self::assertSame($category->getTitle(), $orderCategory['title']);
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createUnverifiedAdmin(): User
    {
        $user = (new User())
            ->setEmail('unverified-category-read-'.str_replace('.', '', uniqid('', true)).'@example.test')
            ->setPassword('not-used')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(false);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
