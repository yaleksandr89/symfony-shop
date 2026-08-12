<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
final class CategoryControllerTest extends WebTestCase
{
    #[TestDox('Активная категория находится по slug')]
    public function testActiveCategoryIsResolvedExplicitlyBySlug(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Explicit category '.$suffix)
            ->setSlug('explicit-category-'.$suffix);
        $product = (new Product())
            ->setTitle('Explicit product '.$suffix)
            ->setPrice('10.00')
            ->setQuantity(1)
            ->setIsPublished(true)
            ->setCategory($category);
        $product->addProductImage(
            (new ProductImage())
                ->setFilenameBig('explicit-'.$suffix.'.jpg')
                ->setFilenameMiddle('explicit-'.$suffix.'.jpg')
                ->setFilenameSmall('explicit-'.$suffix.'.jpg')
        );

        $entityManager = $this->getEntityManager();
        $entityManager->persist($category);
        $entityManager->persist($product);
        $entityManager->flush();

        $url = $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $category->getSlug(),
        ]);
        self::assertSame('/ru/category/'.$category->getSlug(), $url);
        self::assertStringNotContainsString('/'.$category->getId(), $url);

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertStringContainsString((string) $category->getTitle(), (string) $client->getResponse()->getContent());
        self::assertStringContainsString((string) $product->getTitle(), (string) $client->getResponse()->getContent());
        self::assertSelectorTextContains('.page-title2', (string) $category->getTitle());
    }

    #[TestDox('Неизвестный slug возвращает 404')]
    public function testUnknownSlugRemainsNotFound(): void
    {
        $slug = 'missing-category-'.str_replace('.', '', uniqid('', true));
        $client = self::createClient();
        $client->request('GET', $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $slug,
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertFalse($client->getResponse()->isRedirect());
    }

    #[TestDox('Страница категории показывает все активные товары и заглушки изображений')]
    public function testCategoryRendersEveryActiveProductAndImagePlaceholder(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Complete category '.$suffix)
            ->setSlug('complete-category-'.$suffix);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($category);

        $activeTitles = [];
        for ($index = 1; $index <= 6; ++$index) {
            $title = sprintf('Complete product %d %s', $index, $suffix);
            $product = (new Product())
                ->setSlug(sprintf('complete-product-%d-%s', $index, $suffix))
                ->setTitle($title)
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setIsPublished(true)
                ->setIsNew(in_array($index, [1, 3], true))
                ->setIsOnSale(in_array($index, [2, 3], true))
                ->setCategory($category);
            if (1 !== $index) {
                $filename = sprintf('complete-%d-%s.jpg', $index, $suffix);
                $product->addProductImage(
                    (new ProductImage())
                        ->setFilenameBig($filename)
                        ->setFilenameMiddle($filename)
                        ->setFilenameSmall($filename)
                );
            }
            $entityManager->persist($product);
            $activeTitles[] = $title;
        }

        foreach ([['Deleted', true, true], ['Unpublished', false, false]] as [$label, $isPublished, $isDeleted]) {
            $entityManager->persist(
                (new Product())
                    ->setSlug(strtolower($label).'-product-'.$suffix)
                    ->setTitle($label.' product '.$suffix)
                    ->setPrice('10.00')
                    ->setQuantity(1)
                    ->setIsPublished($isPublished)
                    ->setIsDeleted($isDeleted)
                    ->setCategory($category)
            );
        }
        $entityManager->flush();

        $collector = self::getContainer()->get('data_collector.doctrine');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $collector->reset();
        $client->enableProfiler();
        $url = $this->getRouter()->generate('main_category_show', [
            '_locale' => 'en',
            'slug' => $category->getSlug(),
        ]);
        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(6, '.product-item');
        self::assertSelectorCount(1, '.product-item .product-image-placeholder');
        self::assertSelectorCount(1, '.product-image-placeholder[aria-label="Image unavailable"]');
        self::assertSelectorCount(1, '.product-image-placeholder .far.fa-image');
        self::assertSelectorCount(6, '.product-item .product-title');
        self::assertSelectorCount(6, '.product-item .product-price');
        self::assertSelectorCount(3, '.product-item .product-merchandising-statuses');
        self::assertSelectorCount(2, '.product-item .sale-status-new');
        self::assertSelectorCount(2, '.product-item .sale-status-sale');
        $ordinaryCard = $crawler->filter('.product-item')->reduce(
            static fn ($node): bool => str_contains($node->text(), $activeTitles[3]),
        );
        self::assertCount(1, $ordinaryCard);
        self::assertCount(0, $ordinaryCard->filter('.product-merchandising-statuses'));
        foreach ($activeTitles as $title) {
            self::assertStringContainsString($title, (string) $client->getResponse()->getContent());
        }
        self::assertStringNotContainsString('Deleted product '.$suffix, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('Unpublished product '.$suffix, (string) $client->getResponse()->getContent());
        $initialQueryCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(4, $initialQueryCount);

        $entityManager = $this->getEntityManager();
        $managedCategory = $entityManager->find(Category::class, $category->getId());
        self::assertInstanceOf(Category::class, $managedCategory);
        for ($index = 7; $index <= 12; ++$index) {
            $entityManager->persist(
                (new Product())
                    ->setSlug(sprintf('complete-product-%d-%s', $index, $suffix))
                    ->setTitle(sprintf('Complete product %d %s', $index, $suffix))
                    ->setPrice('10.00')
                    ->setQuantity(1)
                    ->setIsPublished(true)
                    ->setCategory($managedCategory)
            );
        }
        $entityManager->flush();

        $collector = self::getContainer()->get('data_collector.doctrine');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $collector->reset();
        $client->enableProfiler();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, '.product-item');
        self::assertSame($initialQueryCount, $this->doctrineQueryCount($client));
    }

    #[TestDox('Удалённая категория перенаправляет на главную и показывает предупреждение')]
    public function testDeletedCategoryKeepsHomepageRedirectAndWarningFlash(): void
    {
        $client = self::createClient();
        $suffix = str_replace('.', '', uniqid('', true));
        $category = (new Category())
            ->setTitle('Deleted category '.$suffix)
            ->setSlug('deleted-category-'.$suffix)
            ->setIsDeleted(true);
        $entityManager = $this->getEntityManager();
        $entityManager->persist($category);
        $entityManager->flush();

        $client->request('GET', $this->getRouter()->generate('main_category_show', [
            '_locale' => 'ru',
            'slug' => $category->getSlug(),
        ]));

        self::assertResponseRedirects(
            $this->getRouter()->generate('main_homepage', ['_locale' => 'ru']),
            Response::HTTP_FOUND
        );
        self::assertSame(
            [sprintf('The category %s not found!', $category->getTitle())],
            $client->getRequest()->getSession()->getFlashBag()->peek('warning')
        );
        self::assertStringNotContainsString((string) $category->getTitle(), (string) $client->getResponse()->getContent());
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function getRouter(): RouterInterface
    {
        return self::getContainer()->get(RouterInterface::class);
    }

    private function doctrineQueryCount(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): int
    {
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        return $collector->getQueryCount();
    }
}
