<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
final class SitemapControllerTest extends WebTestCase
{
    #[TestDox('sitemap.xml содержит только правдивую главную страницу без запросов к каталогу')]
    public function testSitemapContainsOnlyHomepageWithoutCatalogQueries(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/xml; charset=UTF-8');
        $content = (string) $client->getResponse()->getContent();
        $xml = simplexml_load_string($content);

        self::assertNotFalse($xml);
        self::assertCount(1, $xml->children()->url);
        self::assertSame('http://localhost/ru/', (string) $xml->children()->url->loc);
        self::assertStringNotContainsString('<lastmod>', $content);
        self::assertStringNotContainsString('<changefreq>', $content);
        self::assertStringNotContainsString('<priority>', $content);

        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertSame(0, $collector->getQueryCount());
    }
}
