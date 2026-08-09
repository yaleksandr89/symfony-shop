<?php

namespace App\Tests\Functional\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class DefaultControllerTest extends WebTestCase
{
    public function testHomepageQueryCountDoesNotGrowWithProducts(): void
    {
        $client = static::createClient();
        $category = $this->createHomepageProducts(12);
        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $client->request('GET', '/ru/');

        self::assertResponseIsSuccessful();
        $initialCount = $this->doctrineQueryCount($client);
        self::assertLessThanOrEqual(8, $initialCount);

        $this->createHomepageProducts(12, $category);
        $this->resetDoctrineQueryLog();
        $client->enableProfiler();
        $client->request('GET', '/ru/');

        self::assertResponseIsSuccessful();
        self::assertSame($initialCount, $this->doctrineQueryCount($client));
    }

    public function testRedirectEmptyUrlToLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects(
            'http://localhost/ru',
            Response::HTTP_MOVED_PERMANENTLY,
            sprintf('The %s URL redirections to the version with locale', '/')
        );
    }

    public function testHomepageUsesCurrentIdentityAndFaviconMetadata(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.logotype img[alt="Alexander Yurchenko — PHP Developer"]');
        self::assertStringContainsString('alexander-yurchenko-php-developer', (string) $client->getCrawler()->filter('.logotype img')->attr('src'));
        self::assertSelectorCount(1, 'link[rel="apple-touch-icon"][href*="/build/images/icons/favicon/apple-touch-icon.png"]');
        self::assertSelectorCount(1, 'link[rel="manifest"][href*="/build/images/icons/favicon/site.webmanifest"]');
        self::assertSelectorCount(1, 'link[rel="shortcut icon"][href*="/build/images/icons/favicon/favicon.ico"]');
        self::assertSelectorCount(1, '.widget-copyright a[href="https://yaleksandr89.github.io/"][target="_blank"][rel="noopener noreferrer"]');
        self::assertSelectorTextContains('.widget-copyright', 'Copyright © Alexander Yurchenko. All rights reserved.');

        $client->request('GET', '/ru/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.widget-copyright', 'Авторское право © Александр Юрченко. Все права защищены.');
    }

    #[DataProvider(methodName: 'getSecureUrls')]
    public function testSecureUrl(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseRedirects(
            '/ru/login',
            Response::HTTP_FOUND,
            sprintf('The %s URL redirections to the login page', $url)
        );
    }

    public static function getSecureUrls(): ?Generator
    {
        yield ['/ru/profile'];
        yield ['/ru/profile/edit'];
    }

    private function createHomepageProducts(int $count, ?Category $category = null): Category
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = str_replace('.', '', uniqid('', true));
        $category ??= (new Category())
            ->setTitle('Homepage query '.$suffix)
            ->setSlug('homepage-query-'.$suffix);
        $entityManager->persist($category);
        for ($index = 1; $index <= $count; ++$index) {
            $filename = sprintf('homepage-%s-%d.jpg', $suffix, $index);
            $product = (new Product())
                ->setTitle(sprintf('Homepage product %s %d', $suffix, $index))
                ->setSlug(sprintf('homepage-product-%s-%d', $suffix, $index))
                ->setPrice('10.00')
                ->setQuantity(1)
                ->setIsPublished(true)
                ->setCategory($category);
            $product->addProductImage(
                (new ProductImage())
                    ->setFilenameBig($filename)
                    ->setFilenameMiddle($filename)
                    ->setFilenameSmall($filename)
            );
            $entityManager->persist($product);
        }
        $entityManager->flush();

        return $category;
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

    private function resetDoctrineQueryLog(): void
    {
        $collector = self::getContainer()->get('data_collector.doctrine');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        $collector->reset();
    }
}
