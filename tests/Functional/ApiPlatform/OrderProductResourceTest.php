<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
class OrderProductResourceTest extends ResourceTestUtils
{
    private const COLLECTION_URI = '/api/order_products';

    #[TestDox('Отдельные GET-маршруты и операции OpenAPI отсутствуют, а команды остаются доступны')]
    public function testStandaloneGetRoutesAndOpenApiOperationsAreAbsentWhileCommandRoutesStayStable(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        $routes = $router->getRouteCollection();

        self::assertNull($routes->get('api_order_products_get_collection'));
        self::assertNull($routes->get('api_order_products_get_item'));

        $postRoute = $routes->get('api_order_products_post_collection');
        self::assertNotNull($postRoute);
        self::assertSame('/api/order_products.{_format}', $postRoute->getPath());
        self::assertSame(['POST'], $postRoute->getMethods());

        $deleteRoute = $routes->get('api_order_products_delete_item');
        self::assertNotNull($deleteRoute);
        self::assertSame('/api/order_products/{id}.{_format}', $deleteRoute->getPath());
        self::assertSame(['DELETE'], $deleteRoute->getMethods());

        $client->request('GET', '/api/docs.jsonopenapi', [], [], ['HTTP_ACCEPT' => 'application/vnd.openapi+json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $document = $this->getResponseDecodedContent($client);
        self::assertArrayHasKey(self::COLLECTION_URI, $document['paths']);
        self::assertArrayHasKey('post', $document['paths'][self::COLLECTION_URI]);
        self::assertArrayNotHasKey('get', $document['paths'][self::COLLECTION_URI]);
        self::assertArrayHasKey(self::COLLECTION_URI.'/{id}', $document['paths']);
        self::assertArrayHasKey('delete', $document['paths'][self::COLLECTION_URI.'/{id}']);
        self::assertArrayNotHasKey('get', $document['paths'][self::COLLECTION_URI.'/{id}']);
    }

    #[TestDox('Анонимный пользователь не создаёт позиции заказа')]
    public function testAnonymousCannotPostWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);

        $this->requestPost($client, $this->validPostPayload($context));

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Обычный пользователь не создаёт позиции заказа')]
    public function testRegularUserCannotPostWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $this->requestPost($client, $this->validPostPayload($context));

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Неверифицированный администратор не создаёт позиции заказа')]
    public function testUnverifiedAdminCannotPostWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $user = $this->createUnverifiedAdmin();
        $before = $this->aggregateSnapshot($context);
        $client->loginUser($user, 'website');

        $this->requestPost($client, $this->validPostPayload($context));

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Верифицированный администратор использует сохранённую цену и точно пересчитывает итог')]
    public function testVerifiedAdminPostUsesPersistedProductPriceAndRecalculatesTotalExactly(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $countBefore = $this->countOrderProducts();

        $this->requestPost($client, $this->validPostPayload($context, '9999999999999.99', 2));

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame($countBefore + 1, $this->countOrderProducts());
        $line = $this->findLine($context['orderId'], $context['freeProductId']);
        self::assertSame(2, $line->getQuantity());
        self::assertSame('89.99', $line->getPricePerOne());
        self::assertSame('320.21', $this->findOrder($context['orderId'])->getTotalPrice());
        self::assertSame('11.10', $this->findOrder($context['unrelatedOrderId'])->getTotalPrice());
        self::assertEmailCount(0);
    }

    #[TestDox('Отсутствующая связь отклоняется до изменения заказа')]
    public function testMissingRelationIsRejectedBeforeMutation(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);

        $this->requestPost($client, [
            'product' => $context['freeProductIri'],
            'quantity' => 1,
            'pricePerOne' => '1.00',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Недопустимое количество отклоняется до изменения заказа')]
    public function testInvalidQuantityIsRejectedBeforeMutation(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);

        $this->requestPost($client, $this->validPostPayload($context, '1.00', 0));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Повторный POST возвращает конфликт без изменения позиции и итога')]
    public function testDuplicatePostReturnsConflictWithoutChangingLineOrTotal(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);

        $this->requestPost($client, [
            'appOrder' => $context['orderIri'],
            'product' => $context['existingProductIri'],
            'quantity' => 99,
            'pricePerOne' => '0.01',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Сбой транзакции откатывает частично созданную позицию и итог')]
    public function testTransactionFailureRollsBackPartialLineAndTotalChange(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $this->setProductPrice($context['freeProductId'], '9999999999999.99');
        $before = $this->aggregateSnapshot($context);

        $this->requestPost($client, $this->validPostPayload($context, '0.01', PHP_INT_MAX));

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Анонимный пользователь не удаляет позиции заказа')]
    public function testAnonymousCannotDeleteWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);

        $this->requestDelete($client, $context['removableLineId']);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Обычный пользователь не удаляет позиции заказа')]
    public function testRegularUserCannotDeleteWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $before = $this->aggregateSnapshot($context);
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $this->requestDelete($client, $context['removableLineId']);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Неверифицированный администратор не удаляет позиции заказа')]
    public function testUnverifiedAdminCannotDeleteWithoutMutatingAnyOrder(): void
    {
        $client = self::createClient();
        $context = $this->createOrderContext();
        $user = $this->createUnverifiedAdmin();
        $before = $this->aggregateSnapshot($context);
        $client->loginUser($user, 'website');

        $this->requestDelete($client, $context['removableLineId']);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        self::assertSame($before, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    #[TestDox('Удаление позиции администратором пересчитывает итог по оставшимся позициям')]
    public function testVerifiedAdminDeleteRecalculatesTotalFromRemainingLines(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext();
        $countBefore = $this->countOrderProducts();

        $this->requestDelete($client, $context['removableLineId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame($countBefore - 1, $this->countOrderProducts());
        self::assertNull($this->findOrderProduct($context['removableLineId']));
        self::assertSame('139.93', $this->findOrder($context['orderId'])->getTotalPrice());
        self::assertSame('11.10', $this->findOrder($context['unrelatedOrderId'])->getTotalPrice());
        self::assertEmailCount(0);
    }

    #[TestDox('Удаление последней позиции устанавливает канонический ноль, повтор возвращает 404')]
    public function testDeletingLastLineSetsCanonicalZeroAndReplayIs404WithoutMutation(): void
    {
        $client = $this->createVerifiedAdminClient();
        $context = $this->createOrderContext(false);

        $this->requestDelete($client, $context['existingLineId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame('0.00', $this->findOrder($context['orderId'])->getTotalPrice());
        self::assertNull($this->findOrderProduct($context['existingLineId']));
        $afterDelete = $this->aggregateSnapshot($context);

        $this->requestDelete($client, $context['existingLineId']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame($afterDelete, $this->aggregateSnapshot($context));
        self::assertEmailCount(0);
    }

    private function createVerifiedAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        return $client;
    }

    /**
     * @return array{
     *     orderId: int,
     *     orderIri: string,
     *     unrelatedOrderId: int,
     *     existingLineId: int,
     *     removableLineId: int,
     *     existingProductIri: string,
     *     freeProductId: int,
     *     freeProductIri: string
     * }
     */
    private function createOrderContext(bool $withRemovableLine = true): array
    {
        $entityManager = $this->entityManager();
        $suffix = str_replace('.', '', uniqid('', true));
        $existingProduct = (new Product())
            ->setTitle('Order command existing '.$suffix)
            ->setPrice('19.99')
            ->setQuantity(100)
            ->setIsPublished(true);
        $removableProduct = (new Product())
            ->setTitle('Order command removable '.$suffix)
            ->setPrice('0.10')
            ->setQuantity(100)
            ->setIsPublished(true);
        $freeProduct = (new Product())
            ->setTitle('Order command free '.$suffix)
            ->setPrice('89.99')
            ->setQuantity(100)
            ->setIsPublished(true);
        $unrelatedProduct = (new Product())
            ->setTitle('Order command unrelated '.$suffix)
            ->setPrice('5.55')
            ->setQuantity(100)
            ->setIsPublished(true);

        $existingLine = (new OrderProduct())
            ->setProduct($existingProduct)
            ->setQuantity(7)
            ->setPricePerOne('19.99');
        $removableLine = (new OrderProduct())
            ->setProduct($removableProduct)
            ->setQuantity(3)
            ->setPricePerOne('0.10');
        $order = (new Order())
            ->setOwner($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL))
            ->setStatus(1)
            ->setTotalPrice($withRemovableLine ? '140.23' : '139.93')
            ->addOrderProduct($existingLine);
        if ($withRemovableLine) {
            $order->addOrderProduct($removableLine);
        }

        $unrelatedLine = (new OrderProduct())
            ->setProduct($unrelatedProduct)
            ->setQuantity(2)
            ->setPricePerOne('5.55');
        $unrelatedOrder = (new Order())
            ->setOwner($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL))
            ->setStatus(1)
            ->setTotalPrice('11.10')
            ->addOrderProduct($unrelatedLine);

        foreach ([$existingProduct, $removableProduct, $freeProduct, $unrelatedProduct, $order, $unrelatedOrder] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $orderId = $order->getId();
        $unrelatedOrderId = $unrelatedOrder->getId();
        $existingLineId = $existingLine->getId();
        $freeProductId = $freeProduct->getId();
        self::assertNotNull($orderId);
        self::assertNotNull($unrelatedOrderId);
        self::assertNotNull($existingLineId);
        self::assertNotNull($freeProductId);

        return [
            'orderId' => $orderId,
            'orderIri' => '/api/orders/'.$orderId,
            'unrelatedOrderId' => $unrelatedOrderId,
            'existingLineId' => $existingLineId,
            'removableLineId' => $withRemovableLine ? (int) $removableLine->getId() : $existingLineId,
            'existingProductIri' => '/api/products/'.$existingProduct->getUuid(),
            'freeProductId' => $freeProductId,
            'freeProductIri' => '/api/products/'.$freeProduct->getUuid(),
        ];
    }

    /** @return array<string, mixed> */
    private function validPostPayload(array $context, string $forgedPrice = '0.01', int $quantity = 1): array
    {
        return [
            'appOrder' => $context['orderIri'],
            'product' => $context['freeProductIri'],
            'quantity' => $quantity,
            'pricePerOne' => $forgedPrice,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function requestPost(KernelBrowser $client, array $payload): void
    {
        $client->request(
            'POST',
            self::COLLECTION_URI,
            [],
            [],
            self::REQUEST_HEADERS,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    private function requestDelete(KernelBrowser $client, int $lineId): void
    {
        $client->request('DELETE', self::COLLECTION_URI.'/'.$lineId, [], [], self::REQUEST_HEADERS);
    }

    /**
     * @return array{
     *     count: int,
     *     total: string|null,
     *     unrelatedTotal: string|null,
     *     lines: array<int, array{orderId: int|null, productId: int|null, quantity: int|null, price: string|null}>
     * }
     */
    private function aggregateSnapshot(array $context): array
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $lines = $entityManager->getRepository(OrderProduct::class)->findBy([], ['id' => 'ASC']);
        $lineState = [];
        foreach ($lines as $line) {
            $lineId = $line->getId();
            self::assertNotNull($lineId);
            $lineState[$lineId] = [
                'orderId' => $line->getAppOrder()?->getId(),
                'productId' => $line->getProduct()?->getId(),
                'quantity' => $line->getQuantity(),
                'price' => $line->getPricePerOne(),
            ];
        }

        return [
            'count' => count($lines),
            'total' => $this->findOrder($context['orderId'])->getTotalPrice(),
            'unrelatedTotal' => $this->findOrder($context['unrelatedOrderId'])->getTotalPrice(),
            'lines' => $lineState,
        ];
    }

    private function setProductPrice(int $productId, string $price): void
    {
        $entityManager = $this->entityManager();
        $product = $entityManager->find(Product::class, $productId);
        self::assertInstanceOf(Product::class, $product);
        $product->setPrice($price);
        $entityManager->flush();
    }

    private function findLine(int $orderId, int $productId): OrderProduct
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $line = $entityManager->getRepository(OrderProduct::class)->findOneBy([
            'appOrder' => $entityManager->getReference(Order::class, $orderId),
            'product' => $entityManager->getReference(Product::class, $productId),
        ]);
        self::assertInstanceOf(OrderProduct::class, $line);

        return $line;
    }

    private function findOrder(int $orderId): Order
    {
        $order = $this->entityManager()->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $order);

        return $order;
    }

    private function findOrderProduct(int $lineId): ?OrderProduct
    {
        return $this->entityManager()->find(OrderProduct::class, $lineId);
    }

    private function countOrderProducts(): int
    {
        return $this->entityManager()->getRepository(OrderProduct::class)->count([]);
    }

    private function getUser(string $email): User
    {
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createUnverifiedAdmin(): User
    {
        $user = (new User())
            ->setEmail('unverified-order-command-'.str_replace('.', '', uniqid('', true)).'@example.test')
            ->setPassword('not-used')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(false);
        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        $registry = self::getContainer()->get(ManagerRegistry::class);
        $manager = $registry->getManager();
        if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
            $manager = $registry->resetManager();
        }
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
