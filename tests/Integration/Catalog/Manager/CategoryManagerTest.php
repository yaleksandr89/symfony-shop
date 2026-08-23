<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Manager;

use App\Catalog\Manager\CategoryManager;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class CategoryManagerTest extends KernelTestCase
{
    #[TestDox('Удаление категории сохраняет soft-delete связанных товаров, не затрагивая посторонние')]
    public function testRemoveSoftDeletesCategoryAndLinkedProductsOnly(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(8));
        $categoryA = $this->category('Category A '.$suffix, 'category-a-'.$suffix);
        $categoryB = $this->category('Category B '.$suffix, 'category-b-'.$suffix);
        $productA1 = $this->product('Product A1 '.$suffix, $categoryA);
        $productA2 = $this->product('Product A2 '.$suffix, $categoryA);
        $productB1 = $this->product('Product B1 '.$suffix, $categoryB);

        foreach ([$categoryA, $categoryB, $productA1, $productA2, $productB1] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $categoryAId = $categoryA->getId();
        $categoryBId = $categoryB->getId();
        $productA1Id = $productA1->getId();
        $productA2Id = $productA2->getId();
        $productB1Id = $productB1->getId();
        self::assertIsInt($categoryAId);
        self::assertIsInt($categoryBId);
        self::assertIsInt($productA1Id);
        self::assertIsInt($productA2Id);
        self::assertIsInt($productB1Id);

        $entityManager->clear();
        $persistedCategoryA = $entityManager->find(Category::class, $categoryAId);
        self::assertInstanceOf(Category::class, $persistedCategoryA);
        (new CategoryManager($entityManager))->remove($persistedCategoryA);

        $entityManager->clear();
        $persistedCategoryA = $entityManager->find(Category::class, $categoryAId);
        $persistedCategoryB = $entityManager->find(Category::class, $categoryBId);
        $persistedProductA1 = $entityManager->find(Product::class, $productA1Id);
        $persistedProductA2 = $entityManager->find(Product::class, $productA2Id);
        $persistedProductB1 = $entityManager->find(Product::class, $productB1Id);

        self::assertInstanceOf(Category::class, $persistedCategoryA);
        self::assertInstanceOf(Category::class, $persistedCategoryB);
        self::assertInstanceOf(Product::class, $persistedProductA1);
        self::assertInstanceOf(Product::class, $persistedProductA2);
        self::assertInstanceOf(Product::class, $persistedProductB1);
        self::assertTrue($persistedCategoryA->getIsDeleted());
        self::assertTrue($persistedProductA1->getIsDeleted());
        self::assertTrue($persistedProductA2->getIsDeleted());
        self::assertFalse($persistedCategoryB->getIsDeleted());
        self::assertFalse($persistedProductB1->getIsDeleted());
    }

    private function category(string $title, string $slug): Category
    {
        return (new Category())
            ->setTitle($title)
            ->setSlug($slug);
    }

    private function product(string $title, Category $category): Product
    {
        return (new Product())
            ->setTitle($title)
            ->setPrice('19.99')
            ->setQuantity(3)
            ->setDescription('CategoryManager integration test.')
            ->setCategory($category);
    }
}
