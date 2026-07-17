<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
class DashboardControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    public function testDashboardAndSharedLayoutAreLocalized(string $locale, array $expected, array $unexpected): void
    {
        $client = static::createClient();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => UserFixtures::USER_ADMIN_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        $crawler = $client->request('GET', sprintf('/%s/admin/dashboard', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected['dashboard'], $crawler->filter('title')->text());
        self::assertSame($expected['dashboard'], trim($crawler->filter('h1')->text()));
        self::assertSame($expected['logout'], trim($crawler->filter('.topbar a[href$="/admin/logout"]')->text()));
        self::assertSame('Copyright © RankedChoice', trim($crawler->filter('footer .copyright')->text()));
        self::assertStringContainsString($expected['layout'], $crawler->filter('title')->text());
        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $crawler->filter('title, h1, .topbar, footer')->text());
        }
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', [
            'dashboard' => 'Панель управления', 'logout' => 'Выйти', 'layout' => 'Панель администратора',
        ], ['Dashboard', 'Logout', 'Admin Panel']];
        yield 'English' => ['en', [
            'dashboard' => 'Dashboard', 'logout' => 'Logout', 'layout' => 'Admin Panel',
        ], ['Панель управления', 'Выйти', 'Панель администратора']];
    }
}
