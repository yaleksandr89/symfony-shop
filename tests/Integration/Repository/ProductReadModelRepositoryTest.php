<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\CategoryRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group(name: 'integration')]
final class ProductReadModelRepositoryTest extends KernelTestCase
{
    public function testScalarCardsCoversLimitsAndHomepageCandidates(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())->setTitle('Read model '.$suffix)->setSlug('read-model-'.$suffix);
        $entityManager->persist($category);
        $products = [];
        for ($index = 1; $index <= 4; ++$index) {
            $product = (new Product())
                ->setTitle(sprintf('Read model product %d %s', $index, $suffix))
                ->setSlug(sprintf('read-model-product-%d-%s', $index, $suffix))
                ->setPrice('10.00')
                ->setQuantity($index)
                ->setIsPublished(true)
                ->setIsNew(1 === $index)
                ->setIsOnSale(2 === $index)
                ->setCategory($category);
            if (4 !== $index) {
                foreach (['first', 'second'] as $position) {
                    $filename = sprintf('%s-%d-%s.jpg', $position, $index, $suffix);
                    $product->addProductImage(
                        (new ProductImage())
                            ->setFilenameBig($filename)
                            ->setFilenameMiddle($filename)
                            ->setFilenameSmall($filename)
                    );
                }
            }
            $entityManager->persist($product);
            $products[] = $product;
        }
        $unpublished = (new Product())
            ->setTitle('Unpublished '.$suffix)
            ->setSlug('unpublished-'.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(false)
            ->setCategory($category);
        $deleted = (new Product())
            ->setTitle('Deleted '.$suffix)
            ->setSlug('deleted-'.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setIsDeleted(true)
            ->setCategory($category);
        $deletedCategory = (new Category())
            ->setTitle('Deleted category '.$suffix)
            ->setSlug('deleted-category-'.$suffix)
            ->setIsDeleted(true);
        $productInDeletedCategory = (new Product())
            ->setTitle('Product in deleted category '.$suffix)
            ->setSlug('product-in-deleted-category-'.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($deletedCategory);
        $hiddenProducts = [$unpublished, $deleted, $productInDeletedCategory];
        foreach ($hiddenProducts as $hiddenProduct) {
            $filename = 'hidden-'.$hiddenProduct->getSlug().'.jpg';
            $hiddenProduct->addProductImage(
                (new ProductImage())
                    ->setFilenameBig($filename)
                    ->setFilenameMiddle($filename)
                    ->setFilenameSmall($filename)
            );
            $entityManager->persist($hiddenProduct);
        }
        $entityManager->persist($deletedCategory);
        $entityManager->flush();

        $productRepository = self::getContainer()->get(ProductRepository::class);
        $rows = $productRepository->findCardRowsByCategoryAndCount($category->getId());
        self::assertCount(4, $rows);
        self::assertNull($rows[0]['cover']);
        self::assertSame($products[2]->getId(), $rows[1]['id']);
        self::assertStringStartsWith('first-3-', $rows[1]['cover']['filenameMiddle']);
        self::assertTrue($rows[3]['isNew']);
        self::assertTrue($rows[2]['isOnSale']);
        self::assertSame([], array_intersect(
            array_map(static fn (Product $product): ?int => $product->getId(), $hiddenProducts),
            array_column($rows, 'id'),
        ));

        $limitedRows = $productRepository->findCardRowsByCategoryAndCount($category->getId(), 2);
        self::assertCount(2, $limitedRows);
        self::assertSame([$products[3]->getId(), $products[2]->getId()], array_column($limitedRows, 'id'));

        $covers = self::getContainer()->get(ProductImageRepository::class)->findFirstCoversByProductIds([
            (int) $products[0]->getId(),
            (int) $products[3]->getId(),
        ]);
        self::assertArrayHasKey((int) $products[0]->getId(), $covers);
        self::assertArrayNotHasKey((int) $products[3]->getId(), $covers);
        self::assertSame([], self::getContainer()->get(ProductImageRepository::class)->findFirstCoversByProductIds([]));

        $candidates = self::getContainer()->get(CategoryRepository::class)->findHomepageBannerCandidates();
        $ownCandidates = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['categoryId'] === $category->getId(),
        ));
        self::assertCount(3, $ownCandidates);
        self::assertNotContains($products[3]->getId(), array_column($ownCandidates, 'productId'));
        self::assertSame([], array_intersect(
            array_map(static fn (Product $product): ?int => $product->getId(), $hiddenProducts),
            array_column($candidates, 'productId'),
        ));
    }
}
