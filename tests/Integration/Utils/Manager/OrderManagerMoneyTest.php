<?php

declare(strict_types=1);

namespace App\Tests\Integration\Utils\Manager;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Utils\Manager\OrderManager;
use App\Utils\Generator\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[Group(name: 'integration')]
class OrderManagerMoneyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private OrderManager $orderManager;

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->orderManager = self::getContainer()->get(OrderManager::class);
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    #[TestWith([
        [['89.99', 3], ['94.90', 1]],
        ['89.99', '94.90'],
        '364.87',
    ])]
    #[TestWith([
        [['0.10', 3], ['19.99', 7]],
        ['0.10', '19.99'],
        '140.23',
    ])]
    public function testCreatesCentCorrectOrderTotalsAndPriceSnapshots(array $lines, array $expectedSnapshots, string $expectedTotal): void
    {
        $user = (new User())
            ->setEmail('order-money-'.uniqid('', true).'@example.test')
            ->setPassword('not-used-by-this-test')
            ->setIsVerified(true);
        $cart = (new Cart())->setToken(TokenGenerator::generateToken());

        $this->entityManager->persist($user);

        foreach ($lines as [$price, $quantity]) {
            $product = (new Product())
                ->setTitle('Order money '.uniqid('', true))
                ->setPrice($price)
                ->setQuantity($quantity);
            $cart->addCartProduct(
                (new CartProduct())
                    ->setProduct($product)
                    ->setQuantity($quantity)
            );
            $this->entityManager->persist($product);
        }

        $this->entityManager->persist($cart);
        $this->entityManager->flush();

        $cartId = $cart->getId();
        $userId = $user->getId();
        self::assertNotNull($cartId);
        self::assertNotNull($userId);

        $this->entityManager->clear();

        /** @var User $hydratedUser */
        $hydratedUser = $this->entityManager->find(User::class, $userId);

        $order = (new Order())
            ->setOwner($hydratedUser)
            ->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);
        $this->orderManager->addOrdersProductsFromCart($order, $cartId);

        self::assertSame($expectedSnapshots, $this->getPriceSnapshots($order));

        $this->orderManager->calculationOrderTotalPrice($order);
        self::assertSame($expectedTotal, $order->getTotalPrice());

        $this->orderManager->calculationOrderTotalPrice($order);

        self::assertSame(OrderStaticStorage::ORDER_STATUS_CREATED, $order->getStatus());
        self::assertSame($expectedSnapshots, $this->getPriceSnapshots($order));
        self::assertSame($expectedTotal, $order->getTotalPrice());

        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $orderId = $order->getId();
        self::assertNotNull($orderId);

        $this->entityManager->clear();

        /** @var Order $reloadedOrder */
        $reloadedOrder = $this->entityManager->find(Order::class, $orderId);
        self::assertSame($expectedTotal, $reloadedOrder->getTotalPrice());

        $normalized = $this->serializer->normalize($reloadedOrder, null, [
            'groups' => ['order:item'],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['orderProducts'],
        ]);
        self::assertIsArray($normalized);
        self::assertSame($expectedTotal, $normalized['totalPrice']);

        $this->orderManager->calculationOrderTotalPrice($reloadedOrder);
        self::assertSame($expectedTotal, $reloadedOrder->getTotalPrice());
    }

    /** @return list<string> */
    private function getPriceSnapshots(Order $order): array
    {
        $snapshots = array_map(
            static function (OrderProduct $orderProduct): string {
                $pricePerOne = $orderProduct->getPricePerOne();
                self::assertNotNull($pricePerOne);

                return $pricePerOne;
            },
            $order->getOrderProducts()->toArray()
        );
        sort($snapshots);

        return $snapshots;
    }
}
