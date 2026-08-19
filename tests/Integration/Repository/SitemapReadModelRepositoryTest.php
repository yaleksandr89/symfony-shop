<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class SitemapReadModelRepositoryTest extends KernelTestCase
{
    #[TestDox('Sitemap-проекции применяют eligibility, возвращают только slug и стабильно сортируются')]
    public function testSitemapRowsApplyEligibilityAndStableOrdering(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $prefix = 'sitemap-'.str_replace('.', '', uniqid('', true)).'-';

        $activeA = $this->createCategory($entityManager, $prefix.'category-a');
        $activeZ = $this->createCategory($entityManager, $prefix.'category-z');
        $invalidProductCategory = $this->createCategory($entityManager, $prefix.'category-invalid-product');
        $deletedCategory = $this->createCategory($entityManager, $prefix.'category-deleted', true);
        $unpublishedOnlyCategory = $this->createCategory($entityManager, $prefix.'category-unpublished-only');
        $deletedOnlyCategory = $this->createCategory($entityManager, $prefix.'category-deleted-only');
        $blankCategory = $this->createCategory($entityManager, $prefix.'category-blank-source');

        $eligibleA = $this->createProduct($entityManager, $prefix.'product-a', $activeA);
        $eligibleZ = $this->createProduct($entityManager, $prefix.'product-z', $activeZ);
        $invalidSlugProduct = $this->createProduct(
            $entityManager,
            $prefix.'product-null-slug-source',
            $invalidProductCategory
        );
        $blankSlugProduct = $this->createProduct(
            $entityManager,
            $prefix.'product-blank-slug-source',
            $activeA
        );
        $unpublished = $this->createProduct(
            $entityManager,
            $prefix.'product-unpublished',
            $activeA,
            false
        );
        $deleted = $this->createProduct(
            $entityManager,
            $prefix.'product-deleted',
            $activeA,
            true,
            true
        );
        $withoutCategory = $this->createProduct(
            $entityManager,
            $prefix.'product-without-category',
            null
        );
        $inDeletedCategory = $this->createProduct(
            $entityManager,
            $prefix.'product-in-deleted-category',
            $deletedCategory
        );
        $inBlankCategory = $this->createProduct(
            $entityManager,
            $prefix.'product-in-blank-category',
            $blankCategory
        );
        $this->createProduct(
            $entityManager,
            $prefix.'product-unpublished-only',
            $unpublishedOnlyCategory,
            false
        );
        $this->createProduct(
            $entityManager,
            $prefix.'product-deleted-only',
            $deletedOnlyCategory,
            true,
            true
        );
        $entityManager->flush();

        $connection = $entityManager->getConnection();
        $connection->executeStatement('UPDATE product SET slug = NULL WHERE id = ?', [$invalidSlugProduct->getId()]);
        $connection->executeStatement("UPDATE product SET slug = '   ' WHERE id = ?", [$blankSlugProduct->getId()]);
        $connection->executeStatement("UPDATE category SET slug = '   ' WHERE id = ?", [$blankCategory->getId()]);
        $entityManager->clear();

        $productRows = self::getContainer()->get(ProductRepository::class)->findSitemapRows();
        $categoryRows = self::getContainer()->get(CategoryRepository::class)->findSitemapRows();

        $this->assertScalarSlugRowsAreSorted($productRows);
        $this->assertScalarSlugRowsAreSorted($categoryRows);

        $productSlugs = array_column($productRows, 'slug');
        $ownProductSlugs = array_values(array_filter(
            $productSlugs,
            static fn (string $slug): bool => str_starts_with($slug, $prefix)
        ));
        $expectedProductSlugs = [
            (string) $eligibleA->getSlug(),
            (string) $eligibleZ->getSlug(),
            (string) $inDeletedCategory->getSlug(),
            (string) $inBlankCategory->getSlug(),
        ];
        sort($expectedProductSlugs);
        self::assertSame($expectedProductSlugs, $ownProductSlugs);
        self::assertSame([], array_intersect([
            (string) $unpublished->getSlug(),
            (string) $deleted->getSlug(),
            (string) $withoutCategory->getSlug(),
            $prefix.'product-null-slug-source',
            $prefix.'product-blank-slug-source',
        ], $productSlugs));

        $categorySlugs = array_column($categoryRows, 'slug');
        $ownCategorySlugs = array_values(array_filter(
            $categorySlugs,
            static fn (string $slug): bool => str_starts_with($slug, $prefix)
        ));
        $expectedCategorySlugs = [
            (string) $activeA->getSlug(),
            (string) $activeZ->getSlug(),
            (string) $invalidProductCategory->getSlug(),
        ];
        sort($expectedCategorySlugs);
        self::assertSame($expectedCategorySlugs, $ownCategorySlugs);
        self::assertSame([], array_intersect([
            (string) $deletedCategory->getSlug(),
            (string) $unpublishedOnlyCategory->getSlug(),
            (string) $deletedOnlyCategory->getSlug(),
            $prefix.'category-blank-source',
        ], $categorySlugs));
    }

    private function createCategory(
        EntityManagerInterface $entityManager,
        string $slug,
        bool $isDeleted = false,
    ): Category {
        $category = (new Category())
            ->setTitle('Sitemap category '.uniqid('', true))
            ->setSlug($slug)
            ->setIsDeleted($isDeleted);
        $entityManager->persist($category);

        return $category;
    }

    private function createProduct(
        EntityManagerInterface $entityManager,
        string $slug,
        ?Category $category,
        bool $isPublished = true,
        bool $isDeleted = false,
    ): Product {
        $product = (new Product())
            ->setTitle('Sitemap product '.uniqid('', true))
            ->setSlug($slug)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished($isPublished)
            ->setIsDeleted($isDeleted)
            ->setCategory($category);
        $entityManager->persist($product);

        return $product;
    }

    /** @param list<array{slug: string}> $rows */
    private function assertScalarSlugRowsAreSorted(array $rows): void
    {
        foreach ($rows as $row) {
            self::assertSame(['slug'], array_keys($row));
            self::assertIsString($row['slug']);
        }

        $slugs = array_column($rows, 'slug');
        $sortedSlugs = $slugs;
        sort($sortedSlugs);
        self::assertSame($sortedSlugs, $slugs);
    }
}
