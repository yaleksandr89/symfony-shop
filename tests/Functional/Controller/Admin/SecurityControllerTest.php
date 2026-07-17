<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group(name: 'functional')]
class SecurityControllerTest extends WebTestCase
{
    #[DataProvider(methodName: 'provideLocales')]
    public function testLoginPageIsLocalized(string $locale, array $expected, array $unexpected): void
    {
        $crawler = static::createClient()->request('GET', sprintf('/%s/admin/login', $locale));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expected['title'], $crawler->filter('title')->text());
        self::assertSame($expected['heading'], trim($crawler->filter('h1')->text()));
        self::assertSame($expected['email'], $crawler->filter('#inputEmail')->attr('placeholder'));
        self::assertSame($expected['password'], $crawler->filter('#inputPassword')->attr('placeholder'));
        self::assertSame($expected['remember'], trim($crawler->filter('label[for="rememberMe"]')->text()));
        self::assertSame($expected['submit'], trim($crawler->filter('button[type="submit"]')->text()));

        foreach ($unexpected as $text) {
            self::assertStringNotContainsString($text, $crawler->filter('.card, title')->text());
        }
    }

    public static function provideLocales(): Generator
    {
        yield 'Russian' => ['ru', [
            'title' => 'Вход', 'heading' => 'Вход в панель администратора',
            'email' => 'Введите электронную почту...', 'password' => 'Пароль',
            'remember' => 'Запомнить меня', 'submit' => 'Войти',
        ], ['Log In', 'Auth Form', 'Enter Email Address...', 'Password', 'Remember Me', 'Login']];

        yield 'English' => ['en', [
            'title' => 'Log In', 'heading' => 'Auth Form',
            'email' => 'Enter Email Address...', 'password' => 'Password',
            'remember' => 'Remember Me', 'submit' => 'Login',
        ], ['Вход в панель администратора', 'Введите электронную почту...', 'Пароль', 'Запомнить меня', 'Войти']];
    }
}
