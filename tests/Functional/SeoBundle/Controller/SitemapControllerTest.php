<?php

declare(strict_types=1);

namespace App\Tests\Functional\SeoBundle\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
final class SitemapControllerTest extends WebTestCase
{
    #[TestDox('sitemap.xml публикует eligible категории и товары canonical slug URL за два запроса')]
    public function testSitemapContainsEligibleCanonicalUrlsInStableOrderWithTwoQueries(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $prefix = 'functional-sitemap-'.str_replace('.', '', uniqid('', true)).'-';

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
        $connection->executeStatement("UPDATE product SET slug = ' ' WHERE id = ?", [$blankSlugProduct->getId()]);
        $connection->executeStatement("UPDATE category SET slug = ' ' WHERE id = ?", [$blankCategory->getId()]);
        $entityManager->clear();

        $collector = self::getContainer()->get('data_collector.doctrine');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $collector->reset();
        $client->enableProfiler();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/xml; charset=UTF-8');
        $content = (string) $client->getResponse()->getContent();
        $xml = simplexml_load_string($content);

        self::assertNotFalse($xml);
        $urls = [];
        foreach ($xml->children()->url as $url) {
            $urls[] = (string) $url->loc;
        }

        self::assertNotEmpty($urls);
        self::assertSame('http://localhost/ru/', $urls[0]);
        foreach ($urls as $url) {
            self::assertStringStartsWith('http://localhost/ru/', $url);
            self::assertStringNotContainsString('/en/', $url);
        }

        $categoryUrls = array_values(array_filter(
            $urls,
            static fn (string $url): bool => str_contains($url, '/ru/category/')
        ));
        $productUrls = array_values(array_filter(
            $urls,
            static fn (string $url): bool => str_contains($url, '/ru/product/')
        ));
        self::assertSame(array_merge(['http://localhost/ru/'], $categoryUrls, $productUrls), $urls);
        $this->assertUrlsAreSortedBySlug($categoryUrls);
        $this->assertUrlsAreSortedBySlug($productUrls);

        $ownCategoryUrls = array_values(array_filter(
            $categoryUrls,
            static fn (string $url): bool => str_contains($url, '/'.$prefix)
        ));
        $expectedCategoryUrls = [
            'http://localhost/ru/category/'.$activeA->getSlug(),
            'http://localhost/ru/category/'.$activeZ->getSlug(),
            'http://localhost/ru/category/'.$invalidProductCategory->getSlug(),
        ];
        sort($expectedCategoryUrls);
        self::assertSame($expectedCategoryUrls, $ownCategoryUrls);

        $ownProductUrls = array_values(array_filter(
            $productUrls,
            static fn (string $url): bool => str_contains($url, '/'.$prefix)
        ));
        $expectedProductUrls = [
            'http://localhost/ru/product/'.$eligibleA->getSlug(),
            'http://localhost/ru/product/'.$eligibleZ->getSlug(),
            'http://localhost/ru/product/'.$inDeletedCategory->getSlug(),
            'http://localhost/ru/product/'.$inBlankCategory->getSlug(),
        ];
        sort($expectedProductUrls);
        self::assertSame($expectedProductUrls, $ownProductUrls);
        self::assertSame([], array_intersect([
            'http://localhost/ru/category/'.$deletedCategory->getSlug(),
            'http://localhost/ru/category/'.$unpublishedOnlyCategory->getSlug(),
            'http://localhost/ru/category/'.$deletedOnlyCategory->getSlug(),
            'http://localhost/ru/product/'.$unpublished->getSlug(),
            'http://localhost/ru/product/'.$deleted->getSlug(),
            'http://localhost/ru/product/'.$withoutCategory->getSlug(),
        ], $urls));
        foreach (array_merge($categoryUrls, $productUrls) as $url) {
            $path = (string) parse_url($url, PHP_URL_PATH);
            self::assertNotSame('', trim(urldecode(basename($path))));
        }
        self::assertStringNotContainsString((string) $eligibleA->getUuid(), $content);
        self::assertStringNotContainsString((string) $eligibleZ->getUuid(), $content);
        self::assertStringNotContainsString('<lastmod>', $content);
        self::assertStringNotContainsString('<changefreq>', $content);
        self::assertStringNotContainsString('<priority>', $content);

        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertSame(2, $collector->getQueryCount());
    }

    private function createCategory(
        EntityManagerInterface $entityManager,
        string $slug,
        bool $isDeleted = false,
    ): Category {
        $category = (new Category())
            ->setTitle('Functional sitemap category '.uniqid('', true))
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
            ->setTitle('Functional sitemap product '.uniqid('', true))
            ->setSlug($slug)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished($isPublished)
            ->setIsDeleted($isDeleted)
            ->setCategory($category);
        $entityManager->persist($product);

        return $product;
    }

    /** @param list<string> $urls */
    private function assertUrlsAreSortedBySlug(array $urls): void
    {
        $slugs = array_map(
            static fn (string $url): string => urldecode(basename((string) parse_url($url, PHP_URL_PATH))),
            $urls
        );
        $sortedSlugs = $slugs;
        sort($sortedSlugs);
        self::assertSame($sortedSlugs, $slugs);
    }
}
