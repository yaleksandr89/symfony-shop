<?php

declare(strict_types=1);

namespace App\Tests\Integration\AdminBundle\Form\Filter;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\AdminBundle\Form\FilterType\OrderFilterFormType;
use App\AdminBundle\Form\FilterType\ProductFilterFormType;
use App\AdminBundle\DTO\OrderFilterModel;
use App\AdminBundle\DTO\ProductFilterModel;
use App\AdminBundle\Filter\ExclusiveDateRangeFilter;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Spiriit\Bundle\FormFilterBundle\Filter\Condition\ConditionInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Doctrine\ORMQuery;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[Group(name: 'integration')]
class ExclusiveDateRangeFilterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[TestDox('Пустой диапазон не создаёт условие фильтра')]
    public function testEmptyRangeDoesNotCreateCondition(): void
    {
        $condition = $this->filter()(
            $this->ormQuery(Product::class, 'stock_item'),
            'stock_item.createdAt',
            $this->values(null, null),
        );

        self::assertNull($condition);
    }

    #[TestDox('Нижняя граница диапазона включительна и нормализуется')]
    public function testLowerRangeIsInclusiveAndNormalized(): void
    {
        $original = new DateTimeImmutable('2024-03-10 17:42:31', new DateTimeZone('Europe/Moscow'));
        $condition = $this->condition('stock_item', $this->values($original, null));
        $parameters = $condition->getParameters();

        self::assertStringContainsString('stock_item.createdAt >= :', $condition->getExpression());
        self::assertCount(1, $parameters);
        [$value, $type] = reset($parameters);
        self::assertSame('2024-03-10 00:00:00.000000 Europe/Moscow', $value->format('Y-m-d H:i:s.u e'));
        self::assertSame(Types::DATETIME_IMMUTABLE, $type);
        self::assertSame('2024-03-10 17:42:31', $original->format('Y-m-d H:i:s'));
    }

    #[TestDox('Верхняя граница диапазона становится началом следующего дня и исключается')]
    public function testUpperRangeIsExclusiveNextCalendarDay(): void
    {
        $original = new DateTimeImmutable('2024-03-31 17:42:31', new DateTimeZone('Europe/Berlin'));
        $condition = $this->condition('purchase_record', $this->values(null, $original));
        $parameters = $condition->getParameters();

        self::assertStringContainsString('purchase_record.createdAt < :', $condition->getExpression());
        self::assertStringNotContainsString('<=', $condition->getExpression());
        self::assertCount(1, $parameters);
        [$value, $type] = reset($parameters);
        self::assertSame('2024-04-01 00:00:00.000000 Europe/Berlin', $value->format('Y-m-d H:i:s.u e'));
        self::assertSame(Types::DATETIME_IMMUTABLE, $type);
        self::assertSame('2024-03-31 17:42:31', $original->format('Y-m-d H:i:s'));
    }

    #[TestDox('Границы диапазона используют разные параметры и произвольный алиас')]
    public function testBothBoundsUseDistinctParametersAndArbitraryAlias(): void
    {
        $condition = $this->condition(
            'catalog_entry',
            $this->values(new DateTimeImmutable('2024-03-10'), new DateTimeImmutable('2024-03-12')),
        );

        self::assertStringContainsString('catalog_entry.createdAt >= :', $condition->getExpression());
        self::assertStringContainsString(' AND catalog_entry.createdAt < :', $condition->getExpression());
        self::assertCount(2, $condition->getParameters());
        self::assertCount(2, array_unique(array_keys($condition->getParameters())));
        self::assertStringNotContainsString('p.createdAt', $condition->getExpression());
        self::assertStringNotContainsString('o.createdAt', $condition->getExpression());
    }

    #[TestDox('Одна дата обозначает полный календарный день')]
    public function testSameDateRepresentsOneWholeCalendarDay(): void
    {
        $date = new DateTimeImmutable('2024-03-10');
        $parameters = array_values($this->condition('record', $this->values($date, $date))->getParameters());

        self::assertSame('2024-03-10 00:00:00', $parameters[0][0]->format('Y-m-d H:i:s'));
        self::assertSame('2024-03-11 00:00:00', $parameters[1][0]->format('Y-m-d H:i:s'));
    }

    #[TestDox('Запросы товаров и заказов соблюдают исключающую границу дня')]
    public function testProductAndOrderQueriesRespectExclusiveDayBoundary(): void
    {
        $timestamps = [
            new DateTimeImmutable('2040-03-10 00:00:00'),
            new DateTimeImmutable('2040-03-10 12:34:56'),
            new DateTimeImmutable('2040-03-10 23:59:59'),
            new DateTimeImmutable('2040-03-11 00:00:00'),
        ];
        $products = $this->createProducts($timestamps);
        $orders = $this->createOrders($timestamps);

        try {
            $this->entityManager->flush();

            $this->assertFilteredIds(
                Product::class,
                ProductFilterFormType::class,
                new ProductFilterModel(),
                $products,
            );
            $this->assertFilteredIds(
                Order::class,
                OrderFilterFormType::class,
                new OrderFilterModel(),
                $orders,
            );
        } finally {
            foreach (array_merge($products, $orders) as $entity) {
                if ($this->entityManager->contains($entity)) {
                    $this->entityManager->remove($entity);
                }
            }
            $this->entityManager->flush();
        }
    }

    #[TestDox('Диапазон итоговой цены заказа включает обе десятичные границы')]
    public function testOrderTotalPriceRangeIncludesBothDecimalBoundaries(): void
    {
        $owner = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $owner);
        $orders = [];

        foreach (['90.60', '94.20', '94.21'] as $totalPrice) {
            $order = (new Order())
                ->setOwner($owner)
                ->setStatus(1)
                ->setTotalPrice($totalPrice);
            $this->entityManager->persist($order);
            $orders[] = $order;
        }

        try {
            $this->entityManager->flush();
            $form = self::getContainer()->get(FormFactoryInterface::class)->create(OrderFilterFormType::class, new OrderFilterModel());
            $form->submit(['totalPrice' => ['left_number' => '90.60', 'right_number' => '94.20']], false);
            self::assertTrue($form->isSynchronized());

            $queryBuilder = $this->entityManager->createQueryBuilder()->select('filtered_order')->from(Order::class, 'filtered_order');
            self::getContainer()->get(FilterBuilderUpdater::class)->addFilterConditions($form, $queryBuilder);
            $resultIds = array_map(static fn (Order $order): ?int => $order->getId(), $queryBuilder->getQuery()->getResult());

            self::assertContains($orders[0]->getId(), $resultIds);
            self::assertContains($orders[1]->getId(), $resultIds);
            self::assertNotContains($orders[2]->getId(), $resultIds);
        } finally {
            foreach ($orders as $order) {
                $this->entityManager->remove($order);
            }
            $this->entityManager->flush();
        }
    }

    /** @param class-string $entityClass */
    private function ormQuery(string $entityClass, string $alias): ORMQuery
    {
        return new ORMQuery($this->entityManager->createQueryBuilder()->select($alias)->from($entityClass, $alias));
    }

    /** @param array{value: array{left_date: array{0: DateTimeImmutable|null}, right_date: array{0: DateTimeImmutable|null}}} $values */
    private function condition(string $alias, array $values): ConditionInterface
    {
        $condition = $this->filter()($this->ormQuery(Product::class, $alias), $alias.'.createdAt', $values);
        self::assertInstanceOf(ConditionInterface::class, $condition);

        return $condition;
    }

    private function filter(): ExclusiveDateRangeFilter
    {
        return self::getContainer()->get(ExclusiveDateRangeFilter::class);
    }

    /**
     * @return array{value: array{left_date: array{0: DateTimeImmutable|null}, right_date: array{0: DateTimeImmutable|null}}}
     */
    private function values(?DateTimeImmutable $lower, ?DateTimeImmutable $upper): array
    {
        return ['value' => ['left_date' => [$lower], 'right_date' => [$upper]]];
    }

    /** @param list<DateTimeImmutable> $timestamps @return list<Product> */
    private function createProducts(array $timestamps): array
    {
        $products = [];

        foreach ($timestamps as $index => $timestamp) {
            $product = (new Product())
                ->setTitle('Exclusive boundary product '.$index.' '.uniqid('', true))
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setDescription(null)
                ->setIsPublished(true)
                ->setCreatedAt($timestamp);
            $this->entityManager->persist($product);
            $products[] = $product;
        }

        return $products;
    }

    /** @param list<DateTimeImmutable> $timestamps @return list<Order> */
    private function createOrders(array $timestamps): array
    {
        $owner = self::getContainer()->get(UserRepository::class)->findOneBy([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
        ]);
        self::assertInstanceOf(User::class, $owner);
        $orders = [];

        foreach ($timestamps as $timestamp) {
            $order = (new Order())
                ->setOwner($owner)
                ->setStatus(1)
                ->setTotalPrice('10.00')
                ->setCreatedAt($timestamp);
            $this->entityManager->persist($order);
            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * @param class-string                      $entityClass
     * @param class-string                      $formType
     * @param ProductFilterModel|OrderFilterModel $model
     * @param list<Product>|list<Order>         $entities
     */
    private function assertFilteredIds(string $entityClass, string $formType, object $model, array $entities): void
    {
        $form = self::getContainer()->get(FormFactoryInterface::class)->create($formType, $model);
        $form->submit(['createdAt' => ['left_date' => '2040-03-10', 'right_date' => '2040-03-10']], false);
        self::assertTrue($form->isSynchronized());

        $queryBuilder = $this->entityManager->createQueryBuilder()->select('boundary_record')->from($entityClass, 'boundary_record');
        self::getContainer()->get(FilterBuilderUpdater::class)->addFilterConditions($form, $queryBuilder);

        $resultIds = array_map(
            static fn (object $entity): ?int => $entity->getId(),
            $queryBuilder->getQuery()->getResult(),
        );
        $entityIds = array_map(static fn (object $entity): ?int => $entity->getId(), $entities);

        self::assertContains($entityIds[0], $resultIds);
        self::assertContains($entityIds[1], $resultIds);
        self::assertContains($entityIds[2], $resultIds);
        self::assertNotContains($entityIds[3], $resultIds);

        $parameters = $queryBuilder->getParameters();
        self::assertCount(2, $parameters);
        foreach ($parameters as $parameter) {
            self::assertSame(Types::DATETIME_IMMUTABLE, $parameter->getType());
        }
    }
}
