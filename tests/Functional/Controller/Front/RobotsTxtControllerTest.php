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
    #[TestDox('Вывод robots.txt не обращается к каталогу')]
    public function testRobotsOutputDoesNotQueryCatalog(): void
    {
        $client = self::createClient();
        $client->enableProfiler();
        $client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/plain; charset=UTF-8');
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', (string) $client->getResponse()->getContent());
        $profile = $client->getProfile();
        self::assertNotFalse($profile);
        self::assertNotNull($profile);
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);
        self::assertSame(0, $collector->getQueryCount());
    }
}
