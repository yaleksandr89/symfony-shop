<?php

declare(strict_types=1);

namespace App\Tests\Integration\Demo;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderProduct;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\User;
use App\Money\DecimalMoney;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tools\Demo\DemoDataInitializer;

#[Group(name: 'integration')]
class DemoDataInitializerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[TestDox('Инициализатор строит идемпотентный каталог и заменяемые заказы')]
    public function testInitializerBuildsIdempotentCatalogAndReplaceableOrders(): void
    {
        $initializer = self::getContainer()->get(DemoDataInitializer::class);
        $first = $initializer->initialize();
        $this->entityManager->clear();

        self::assertSame(3, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM \"user\" WHERE email LIKE '%@example.test'"));
        self::assertSame(6, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM category WHERE slug LIKE 'demo-%'"));
        self::assertSame(48, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%'"));
        self::assertSame(47, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product_image pi JOIN product p ON p.id = pi.product_id WHERE p.slug LIKE 'demo-%'"));
        self::assertSame(11, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = true"));
        self::assertSame(8, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_on_sale = true"));
        self::assertSame(29, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = false AND is_on_sale = false"));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product WHERE slug LIKE 'demo-%' AND is_new = true AND is_on_sale = true"));
        self::assertSame(24, $this->entityManager->getRepository(Order::class)->count([]));
        self::assertSame(48, $this->entityManager->getRepository(OrderProduct::class)->count([]));
        $this->assertOrderTotalsAreCanonical();
        self::assertSame(24, $first['orders']['created']);
        self::assertSame(48, $first['order_products']['created']);
        self::assertSame(
            [
                'demo-accessories' => 7,
                'demo-apparel' => 12,
                'demo-boots' => 6,
                'demo-sale' => 9,
                'demo-sandals' => 10,
                'demo-sneakers' => 4,
            ],
            $this->demoProductCountsByCategory(),
        );
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM product_image pi JOIN product p ON p.id = pi.product_id WHERE p.slug = 'demo-warmup-vest'"));

        $stableIds = $this->entityManager->getConnection()->fetchFirstColumn("SELECT id FROM product WHERE slug LIKE 'demo-%' ORDER BY slug");
        $second = $initializer->initialize();
        $this->entityManager->clear();

        self::assertSame($stableIds, $this->entityManager->getConnection()->fetchFirstColumn("SELECT id FROM product WHERE slug LIKE 'demo-%' ORDER BY slug"));
        self::assertSame(0, $second['products']['created']);
        self::assertSame(48, $second['products']['updated'] + $second['products']['existing']);
        self::assertSame(['copied' => 0, 'updated' => 0, 'existing' => 141], $second['image_files']);
        self::assertSame(['removed' => 24, 'created' => 24], $second['orders']);
        self::assertSame(['removed' => 48, 'created' => 48], $second['order_products']);
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM order_product op LEFT JOIN "order" o ON o.id = op.app_order_id WHERE o.id IS NULL'));
        $this->assertOrderTotalsAreCanonical();
    }

    /** @return array<string, int> */
    private function demoProductCountsByCategory(): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(<<<'SQL'
            SELECT c.slug, COUNT(p.id) AS product_count
            FROM category c
            JOIN product p ON p.category_id = c.id
            WHERE p.slug LIKE 'demo-%'
            GROUP BY c.slug
            ORDER BY c.slug
            SQL);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['slug']] = (int) $row['product_count'];
        }

        return $counts;
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
