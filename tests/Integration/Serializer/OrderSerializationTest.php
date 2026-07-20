<?php

declare(strict_types=1);

namespace App\Tests\Integration\Serializer;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

#[Group(name: 'integration')]
class OrderSerializationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testOrderItemNormalizationEmbedsLinesWithoutTheirOrderBackReference(): void
    {
        [$order, $expectedLines] = $this->createOrderWithTwoLines();
        $orderId = $order->getId();
        self::assertNotNull($orderId);

        $this->entityManager->clear();

        $reloadedOrder = $this->entityManager->find(Order::class, $orderId);
        self::assertInstanceOf(Order::class, $reloadedOrder);

        $normalized = $this->serializer->normalize($reloadedOrder, null, ['groups' => ['order:item']]);

        self::assertIsArray($normalized);
        self::assertSame('274.88', $normalized['totalPrice']);
        self::assertIsArray($normalized['orderProducts']);
        self::assertCount(2, $normalized['orderProducts']);

        $linesById = [];
        foreach ($normalized['orderProducts'] as $line) {
            self::assertIsArray($line);
            self::assertArrayHasKey('id', $line);
            self::assertIsInt($line['id']);
            self::assertArrayNotHasKey('appOrder', $line);
            self::assertIsArray($line['product']);
            self::assertArrayNotHasKey('orderProducts', $line['product']);
            self::assertIsArray($line['product']['category']);
            self::assertArrayNotHasKey('products', $line['product']['category']);

            $linesById[$line['id']] = $line;
        }

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

    /**
     * @return array{Order, list<array{id: int, quantity: int, pricePerOne: string, productId: int, productTitle: string, productPrice: string, categoryId: int, categoryTitle: string}>}
     */
    private function createOrderWithTwoLines(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = (new User())
            ->setEmail('order-serialization-'.$suffix.'@example.test')
            ->setPassword('not-used-by-this-test')
            ->setIsVerified(true);
        $firstCategory = (new Category())->setTitle('Serialization boots '.$suffix);
        $secondCategory = (new Category())->setTitle('Serialization socks '.$suffix);
        $firstProduct = (new Product())
            ->setTitle('Serialization Trail Boot '.$suffix)
            ->setPrice('89.99')
            ->setQuantity(100)
            ->setCategory($firstCategory);
        $secondProduct = (new Product())
            ->setTitle('Serialization Merino Sock '.$suffix)
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
            ->setOwner($user)
            ->setStatus(1)
            ->setTotalPrice('274.88');
        $order->addOrderProduct($firstLine);
        $order->addOrderProduct($secondLine);

        foreach ([$user, $firstCategory, $secondCategory, $firstProduct, $secondProduct, $order] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

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
                'pricePerOne' => '94.9',
                'productId' => $secondProduct->getId(),
                'productTitle' => $secondProduct->getTitle(),
                'productPrice' => '94.9',
                'categoryId' => $secondCategory->getId(),
                'categoryTitle' => $secondCategory->getTitle(),
            ],
        ]];
    }
}
