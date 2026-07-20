<?php

declare(strict_types=1);

namespace App\Tests\Integration\Demo;

use App\Demo\DemoDataInitializer;
use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Utils\Money\DecimalMoney;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
class DemoDataInitializerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testInitializerBuildsIdempotentCatalogAndReplaceableOrders(): void
    {
        $initializer = self::getContainer()->get(DemoDataInitializer::class);
        $first = $initializer->initialize();
        $this->entityManager->clear();

        self::assertSame(3, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM \"user\" WHERE email LIKE '%@example.test'"));
        self::assertSame(6, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM category WHERE slug LIKE 'demo-%'"));
        self::assertSame(24, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%'"));
        self::assertSame(24, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product_image pi JOIN product p ON p.id = pi.product_id WHERE p.slug LIKE 'demo-%'"));
        self::assertSame(24, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertSame(48, $this->entityManager->getRepository(OrderProduct::class)->count([]));
        $this->assertOrderTotalsAreCanonical();
        self::assertSame(24, $first['orders']['created']);
        self::assertSame(48, $first['order_products']['created']);
        self::assertSame(6, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM (SELECT category_id FROM product WHERE slug LIKE 'demo-%' GROUP BY category_id HAVING COUNT(*) = 4) grouped"));

        $stableIds = $this->entityManager->getConnection()->fetchFirstColumn("SELECT id FROM product WHERE slug LIKE 'demo-%' ORDER BY slug");
        $second = $initializer->initialize();
        $this->entityManager->clear();

        self::assertSame($stableIds, $this->entityManager->getConnection()->fetchFirstColumn("SELECT id FROM product WHERE slug LIKE 'demo-%' ORDER BY slug"));
        self::assertSame(0, $second['products']['created']);
        self::assertSame(24, $second['products']['updated'] + $second['products']['existing']);
        self::assertSame(['copied' => 0, 'updated' => 0, 'existing' => 72], $second['image_files']);
        self::assertSame(['removed' => 24, 'created' => 24], $second['orders']);
        self::assertSame(['removed' => 48, 'created' => 48], $second['order_products']);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM order_product op LEFT JOIN "order" o ON o.id = op.app_order_id WHERE o.id IS NULL'));
        $this->assertOrderTotalsAreCanonical();
    }

    private function assertOrderTotalsAreCanonical(): void
    {
        foreach ($this->entityManager->getRepository(Order::class)->findAll() as $order) {
            $totalCents = 0;
            foreach ($order->getOrderProducts() as $line) {
                $pricePerOne = $line->getPricePerOne();
                $quantity = $line->getQuantity();
                self::assertNotNull($pricePerOne);
                self::assertNotNull($quantity);
                $totalCents = DecimalMoney::addCents(
                    $totalCents,
                    DecimalMoney::multiplyToCents($pricePerOne, $quantity),
                );
            }

            self::assertSame(DecimalMoney::fromCents($totalCents), $order->getTotalPrice());
            self::assertMatchesRegularExpression('/^\\d+\\.\\d{2}$/', (string) $order->getTotalPrice());
        }
    }
}
