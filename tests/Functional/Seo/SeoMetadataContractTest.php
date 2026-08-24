<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
final class SeoMetadataContractTest extends WebTestCase
{
    #[TestDox('Язык документа следует локали, а устаревшие keywords отсутствуют')]
    public function testDocumentLanguageFollowsLocaleAndKeywordsAreAbsent(): void
    {
        $client = self::createClient();

        $client->request('GET', '/ru/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="ru"]');
        self::assertSelectorNotExists('meta[name="keywords"]');

        $client->request('GET', '/en/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="en"]');
        self::assertSelectorNotExists('meta[name="keywords"]');
    }

    #[TestDox('Метаданные товара используют описание или title fallback и canonical slug URL для UUID и slug')]
    public function testProductMetadataAndCanonicalConvergeForUuidAndSlug(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $title = 'SEO product '.$suffix;
        $description = 'Meaningful product description '.$suffix;
        $slug = 'seo-product-'.$suffix;
        $product = $this->createVisibleProduct($title, $description, $slug);
        $canonicalUrl = 'http://localhost/en/product/'.$slug;

        $client->request('GET', '/en/product/'.$product->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSame($title, $this->selectorContent($client, 'title'));
        self::assertSame($description, $this->selectorContent($client, 'meta[name="description"]', 'content'));
        self::assertSelectorNotExists('meta[name="keywords"]');
        self::assertStringNotContainsString('...', $this->selectorContent($client, 'meta[name="description"]', 'content'));
        self::assertSame($canonicalUrl, $this->selectorContent($client, 'link[rel="canonical"]', 'href'));

        $client->request('GET', '/en/product/'.$slug);
        self::assertResponseIsSuccessful();
        self::assertSame($canonicalUrl, $this->selectorContent($client, 'link[rel="canonical"]', 'href'));

        $fallbackTitle = 'SEO fallback product '.$suffix;
        $fallbackProduct = $this->createVisibleProduct(
            $fallbackTitle,
            '',
            'seo-fallback-product-'.$suffix
        );
        $client->request('GET', '/ru/product/'.$fallbackProduct->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSame($fallbackTitle, $this->selectorContent($client, 'meta[name="description"]', 'content'));
    }

    #[TestDox('Метаданные категории не содержат английский префикс или keywords')]
    public function testCategoryMetadataUsesCategoryTitleWithoutLegacyPrefixOrKeywords(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('SEO category '.$suffix)
            ->setSlug('seo-category-'.$suffix);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->flush();

        $client->request('GET', '/ru/category/'.$category->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSame((string) $category->getTitle(), $this->selectorContent($client, 'title'));
        self::assertSame((string) $category->getTitle().'!', $this->selectorContent($client, 'meta[name="description"]', 'content'));
        self::assertSelectorNotExists('meta[name="keywords"]');
        self::assertStringNotContainsString('Category -', $this->selectorContent($client, 'title'));
        self::assertStringNotContainsString('...', $this->selectorContent($client, 'meta[name="description"]', 'content'));
    }

    #[TestDox('Страница корзины сохраняет переведённый заголовок без placeholder и keywords')]
    public function testCartMetadataPreservesTranslatedTitleWithoutPlaceholderOrKeywords(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/cart');

        self::assertResponseIsSuccessful();
        self::assertSame('Cart', $this->selectorContent($client, 'title'));
        self::assertSame('Symfony shop - pet project', $this->selectorContent($client, 'meta[name="description"]', 'content'));
        self::assertSelectorNotExists('meta[name="keywords"]');
        self::assertStringNotContainsString('...', $this->selectorContent($client, 'meta[name="description"]', 'content'));
    }

    private function createVisibleProduct(string $title, string $description, string $slug): Product
    {
        $category = (new Category())
            ->setTitle('SEO category '.uniqid('', true))
            ->setSlug('seo-product-category-'.uniqid('', true));
        $product = (new Product())
            ->setTitle($title)
            ->setSlug($slug)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setDescription($description)
            ->setIsPublished(true)
            ->setCategory($category);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    private function selectorContent(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $selector, ?string $attribute = null): string
    {
        $node = $client->getCrawler()->filter($selector);
        self::assertCount(1, $node);

        return $attribute ? (string) $node->attr($attribute) : trim($node->text());
    }
}
