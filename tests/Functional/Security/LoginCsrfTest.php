<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class LoginCsrfTest extends WebTestCase
{
    #[DataProvider('rejectedLogins')]
    #[TestDox('Вход без действительного CSRF не создаёт аутентификацию или remember-me cookie')]
    public function testLoginRequiresValidCsrf(string $locale, bool $admin, ?string $csrf): void
    {
        $client = self::createClient();
        $loginPath = '/'.$locale.($admin ? '/admin/login' : '/login');
        $client->request('GET', 'https://localhost'.$loginPath);
        $parameters = [
            'email' => $admin ? UserFixtures::USER_ADMIN_1_EMAIL : UserFixtures::USER_1_EMAIL,
            'password' => $admin ? 'test2test2' : 'test3test3',
            '_remember_me' => '1',
        ];
        if (null !== $csrf) {
            $parameters['_csrf_token'] = $csrf;
        }

        $client->request('POST', 'https://localhost'.$loginPath, $parameters);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-danger');
        self::assertNull($client->getCookieJar()->get('REMEMBERME'));
        self::assertNull($client->getCookieJar()->get('ADMIN_REMEMBERME'));
        $client->request('GET', '/'.$locale.'/profile');
        self::assertResponseRedirects('/'.$locale.'/login', Response::HTTP_FOUND);
        $client->request('GET', '/'.$locale.'/admin/dashboard');
        self::assertResponseRedirects('/'.$locale.'/admin/login', Response::HTTP_FOUND);
    }

    #[DataProvider('loginForms')]
    #[TestDox('Действительный CSRF сохраняет успешный вход и локализованное перенаправление')]
    public function testRenderedTokenAllowsLogin(string $locale, bool $admin): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/'.$locale.($admin ? '/admin/login' : '/login'));
        $client->submit($crawler->filter('form')->form([
            'email' => $admin ? UserFixtures::USER_ADMIN_1_EMAIL : UserFixtures::USER_1_EMAIL,
            'password' => $admin ? 'test2test2' : 'test3test3',
        ]));

        self::assertResponseRedirects('/'.$locale.($admin ? '/admin/dashboard' : '/profile'), Response::HTTP_FOUND);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string, bool}> */
    public static function loginForms(): iterable
    {
        foreach (['ru', 'en'] as $locale) {
            yield $locale.' front' => [$locale, false];
            yield $locale.' admin' => [$locale, true];
        }
    }

    /** @return iterable<string, array{string, bool, ?string}> */
    public static function rejectedLogins(): iterable
    {
        foreach (self::loginForms() as $name => [$locale, $admin]) {
            yield $name.' missing' => [$locale, $admin, null];
            yield $name.' invalid' => [$locale, $admin, 'invalid-csrf'];
        }
    }
}
