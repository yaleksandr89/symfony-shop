<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
final class RobotsTxtControllerTest extends WebTestCase
{
    #[TestDox('robots.txt разрешает публичную индексацию без запросов к каталогу')]
    public function testRobotsOutputAllowsPublicStorefrontWithoutCatalogQueries(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        $content = (string) $client->getResponse()->getContent();

        self::assertSame(
            "User-agent: *\nDisallow:\n\nSitemap: http://localhost/sitemap.xml",
            trim($content)
        );
        self::assertStringNotContainsString('Disallow: /', $content);
        self::assertStringNotContainsString('Yandex', $content);
        self::assertStringNotContainsString('GoogleBot', $content);
        self::assertStringNotContainsString('Для рабочего сайта', $content);
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertSame(0, $collector->getQueryCount());
    }
}
