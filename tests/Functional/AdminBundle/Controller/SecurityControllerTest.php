<?php

declare(strict_types=1);

namespace App\Tests\Functional\AdminBundle\Controller;

use App\Tests\TestUtils\Fixtures\UserFixtures;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
class SecurityControllerTest extends WebTestCase
{
    #[TestDox('Обычный ROLE_USER получает стандартный отказ и не входит в admin firewall')]
    public function testOrdinaryUserIsRejectedByAdminLoginWithoutRoleDisclosure(): void
    {
        $client = static::createClient();
        $invalidPasswordMessage = $this->authenticationFailureMessage(
            $client,
            UserFixtures::USER_ADMIN_1_EMAIL,
            'wrong-password',
        );

        $client->getCookieJar()->clear();
        $ordinaryUserMessage = $this->authenticationFailureMessage(
            $client,
            UserFixtures::USER_1_EMAIL,
            'test3test3',
        );

        self::assertSame($invalidPasswordMessage, $ordinaryUserMessage);
        self::assertSame('Недействительные аутентификационные данные.', $ordinaryUserMessage);
        self::assertStringNotContainsString(strtolower(UserFixtures::USER_1_EMAIL), strtolower($ordinaryUserMessage));
        self::assertStringNotContainsString('role', strtolower($ordinaryUserMessage));

        $client->request('GET', '/ru/admin/dashboard');
        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
    }

    #[TestDox('Успешный admin login возвращает на сохранённый защищённый TargetPath')]
    public function testSuccessfulLoginRedirectsToSavedAdminTargetPath(): void
    {
        $client = static::createClient();
        $client->request('GET', '/ru/admin/product/list');
        $savedTargetPath = $client->getRequest()->getUri();
        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);

        $loginPage = $client->followRedirect();
        $client->submit($loginPage->selectButton('Войти')->form([
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
            'password' => 'test2test2',
        ]));

        $defaultDashboard = self::getContainer()->get(RouterInterface::class)->generate(
            'admin_dashboard_show',
            ['_locale' => 'ru'],
        );
        self::assertResponseRedirects($savedTargetPath, Response::HTTP_FOUND);
        self::assertNotSame($defaultDashboard, $client->getResponse()->headers->get('location'));
    }

    #[DataProvider(methodName: 'provideLocales')]
    #[TestDox('Страница входа администратора локализована')]
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

    private function authenticationFailureMessage(
        KernelBrowser $client,
        string $email,
        string $password,
    ): string {
        $client->request('GET', '/ru/admin/login');
        $client->submitForm('Войти', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertResponseIsSuccessful();

        return trim((string) preg_replace('/\s*×$/u', '', $client->getCrawler()->filter('.alert-danger')->text()));
    }
}
