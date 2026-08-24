<?php

declare(strict_types=1);

namespace App\Tests\Functional\Commerce\ApiPlatform;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\Tests\Functional\ApiPlatform\ResourceTestUtils;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use App\Money\DecimalMoney;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class OrderResourceTest extends ResourceTestUtils
{
    #[TestDox('Анонимный пользователь не получает коллекцию заказов')]
    public function testAnonymousUserCannotGetOrderCollection(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/orders', [], [], self::REQUEST_HEADERS);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
    }

    #[TestDox('Обычный пользователь не получает коллекцию заказов')]
    public function testRegularUserCannotGetOrderCollection(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $client->request('GET', '/api/orders', [], [], self::REQUEST_HEADERS);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
    }

    #[TestDox('Администратор получает коллекцию заказов')]
    public function testAdminCanGetOrderCollection(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $client->request('GET', '/api/orders', [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    #[TestDox('Администратор получает заказ с вложенными товарами и категориями')]
    public function testAdminCanGetOrderItemWithEmbeddedProductsAndCategories(): void
    {
        $client = self::createClient();
        [$order, $expectedLines] = $this->createOrderWithTwoLines();
        $orderId = $order->getId();
        self::assertNotNull($orderId);

        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $uri = '/api/orders/'.$orderId;
        $client->request('GET', $uri, [], [], self::REQUEST_HEADERS);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $document = $this->getResponseDecodedContent($client);
        self::assertSame('/api/contexts/Order', $document['@context']);
        self::assertSame($uri, $document['@id']);
        self::assertSame('Order', $document['@type']);
        self::assertSame($orderId, $document['id']);
        self::assertSame('274.88', $document['totalPrice']);
        self::assertIsArray($document['orderProducts']);
        self::assertCount(2, $document['orderProducts']);

        $actualCents = 0;
        $linesById = [];
        foreach ($document['orderProducts'] as $line) {
            self::assertIsArray($line);
            self::assertArrayNotHasKey('appOrder', $line);
            self::assertArrayNotHasKey('orderProducts', $line);
            self::assertIsArray($line['product']);
            self::assertArrayNotHasKey('orderProducts', $line['product']);
            self::assertIsArray($line['product']['category']);
            self::assertArrayNotHasKey('products', $line['product']['category']);

            $actualCents += DecimalMoney::toCents($line['pricePerOne']) * $line['quantity'];
            $linesById[$line['id']] = $line;
        }
        self::assertSame(DecimalMoney::toCents($document['totalPrice']), $actualCents);

        foreach ($expectedLines as $expectedLine) {
            $line = $linesById[$expectedLine['id']];
            self::assertSame($expectedLine['quantity'], $line['quantity']);
            self::assertSame($expectedLine['pricePerOne'], $line['pricePerOne']);
            self::assertSame($expectedLine['productId'], $line['product']['id']);
            self::assertSame($expectedLine['productTitle'], $line['product']['title']);
            self::assertSame($expectedLine['productPrice'], $line['product']['price']);
            self::assertSame($expectedLine['categoryId'], $line['product']['category']['id']);
            self::assertSame($expectedLine['categoryTitle'], $line['product']['category']['title']);
        }
    }

    #[TestDox('Обычный пользователь не получает отдельный заказ')]
    public function testRegularUserCannotGetOrderItem(): void
    {
        $client = self::createClient();
        $order = $this->createOrderWithTwoLines()[0];
        $orderId = $order->getId();
        self::assertNotNull($orderId);

        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $client->request('GET', '/api/orders/'.$orderId, [], [], self::REQUEST_HEADERS);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
    }

    #[TestDox('Анонимный пользователь не получает отдельный заказ')]
    public function testAnonymousUserCannotGetOrderItem(): void
    {
        $client = self::createClient();
        $order = $this->createOrderWithTwoLines()[0];
        $orderId = $order->getId();
        self::assertNotNull($orderId);

        $client->request('GET', '/api/orders/'.$orderId, [], [], self::REQUEST_HEADERS);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{Order, list<array{id: int, quantity: int, pricePerOne: string, productId: int, productTitle: string, productPrice: string, categoryId: int, categoryTitle: string}>}
     */
    private function createOrderWithTwoLines(): array
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $firstCategory = (new Category())->setTitle('API boots '.$suffix);
        $secondCategory = (new Category())->setTitle('API socks '.$suffix);
        $firstProduct = (new Product())
            ->setTitle('API Trail Boot '.$suffix)
            ->setPrice('89.99')
            ->setQuantity(100)
            ->setCategory($firstCategory);
        $secondProduct = (new Product())
            ->setTitle('API Merino Sock '.$suffix)
            ->setPrice('94.90')
            ->setQuantity(50)
            ->setCategory($secondCategory);
        $firstLine = (new OrderProduct())
            ->setProduct($firstProduct)
            ->setQuantity(2)
            ->setPricePerOne('89.99');
        $secondLine = (new OrderProduct())
            ->setProduct($secondProduct)
            ->setQuantity(1)
            ->setPricePerOne('94.90');
        $order = (new Order())
            ->setOwner($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL))
            ->setStatus(1)
            ->setTotalPrice('274.88');
        $order->addOrderProduct($firstLine);
        $order->addOrderProduct($secondLine);

        foreach ([$firstCategory, $secondCategory, $firstProduct, $secondProduct, $order] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        foreach ([$firstLine, $secondLine, $firstProduct, $secondProduct, $firstCategory, $secondCategory] as $entity) {
            self::assertNotNull($entity->getId());
        }

        return [$order, [
            [
                'id' => $firstLine->getId(),
                'quantity' => 2,
                'pricePerOne' => '89.99',
                'productId' => $firstProduct->getId(),
                'productTitle' => $firstProduct->getTitle(),
                'productPrice' => '89.99',
                'categoryId' => $firstCategory->getId(),
                'categoryTitle' => $firstCategory->getTitle(),
            ],
            [
                'id' => $secondLine->getId(),
                'quantity' => 1,
                'pricePerOne' => '94.90',
                'productId' => $secondProduct->getId(),
                'productTitle' => $secondProduct->getTitle(),
                'productPrice' => '94.90',
                'categoryId' => $secondCategory->getId(),
                'categoryTitle' => $secondCategory->getTitle(),
            ],
        ]];
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
