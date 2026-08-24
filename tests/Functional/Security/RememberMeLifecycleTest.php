<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class RememberMeLifecycleTest extends WebTestCase
{
    #[TestDox('HTTPS-вход на фронтенде создаёт защищённую remember-me cookie')]
    public function testFrontHttpsLoginIssuesHardenedRememberMeCookie(): void
    {
        $this->issueFrontRememberMeCookie(static::createClient());
    }

    #[TestDox('HTTPS-вход в админ-панели создаёт отдельную защищённую remember-me cookie')]
    public function testAdminHttpsLoginIssuesHardenedRememberMeCookie(): void
    {
        $this->issueAdminRememberMeCookie(static::createClient());
    }

    #[TestDox('Фронтальная cookie «запомнить меня» восстанавливает только фронтальную аутентификацию')]
    public function testFrontRememberMeCookieRestoresOnlyFrontAuthentication(): void
    {
        $client = static::createClient();
        $rememberMeCookie = $this->issueFrontRememberMeCookie($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMeCookie);
        $client->request('GET', '/ru/profile');

        self::assertResponseIsSuccessful();
    }

    #[TestDox('Фронтальная cookie «запомнить меня» не аутентифицирует административный firewall')]
    public function testFrontRememberMeCookieCannotAuthenticateAdminFirewall(): void
    {
        $client = static::createClient();
        $rememberMeCookie = $this->issueFrontRememberMeCookie($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMeCookie);
        $client->request('GET', '/ru/admin/dashboard');

        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
    }

    #[TestDox('Административная cookie «запомнить меня» восстанавливает оба локализованных админ-маршрута')]
    public function testAdminRememberMeCookieRestoresBothLocalizedAdminRoutes(): void
    {
        $client = static::createClient();
        $rememberMeCookie = $this->issueAdminRememberMeCookie($client);

        foreach (['ru', 'en'] as $locale) {
            $client->getCookieJar()->clear();
            $client->getCookieJar()->set($rememberMeCookie);
            $client->request('GET', sprintf('/%s/admin/dashboard', $locale));

            self::assertResponseIsSuccessful();
        }
    }

    #[TestDox('Административная cookie «запомнить меня» не аутентифицирует фронтальный firewall')]
    public function testAdminRememberMeCookieCannotAuthenticateFrontFirewall(): void
    {
        $client = static::createClient();
        $rememberMeCookie = $this->issueAdminRememberMeCookie($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMeCookie);
        $client->request('GET', '/ru/profile');

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
    }

    #[TestDox('Общая сессия сайта сохраняет доступ, соответствующий роли')]
    public function testSharedWebsiteSessionRetainsRoleAppropriateAccess(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');
        $client->request('GET', '/ru/profile');
        self::assertResponseIsSuccessful();

        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');
        $client->request('GET', '/ru/admin/dashboard');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/ru/profile');
        self::assertResponseIsSuccessful();
    }

    #[TestDox('Выход из фронтенда удаляет обе cookie «запомнить меня»')]
    public function testFrontLogoutDeletesBothRememberMeCookies(): void
    {
        $client = static::createClient();
        [$frontCookie, $adminCookie] = $this->issueBothRememberMeCookies($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($frontCookie);
        $client->getCookieJar()->set($adminCookie);
        $client->request('GET', '/ru/logout');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertBothRememberMeCookiesAreCleared($client);

        $client->request('GET', '/ru/profile');
        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);

        $client->request('GET', '/ru/admin/dashboard');
        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
    }

    #[TestDox('Выход из админ-панели удаляет обе cookie «запомнить меня»')]
    public function testAdminLogoutDeletesBothRememberMeCookies(): void
    {
        $client = static::createClient();
        [$frontCookie, $adminCookie] = $this->issueBothRememberMeCookies($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($frontCookie);
        $client->getCookieJar()->set($adminCookie);
        $client->request('GET', '/ru/admin/logout');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertBothRememberMeCookiesAreCleared($client);

        $client->request('GET', '/ru/profile');
        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);

        $client->request('GET', '/ru/admin/dashboard');
        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
    }

    private function issueFrontRememberMeCookie(KernelBrowser $client): Cookie
    {
        $client->request('GET', 'https://localhost/ru/login');
        $client->submitForm('Авторизоваться', [
            'email' => UserFixtures::USER_1_EMAIL,
            'password' => 'test3test3',
            '_remember_me' => true,
        ]);

        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);

        $rememberMeCookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertInstanceOf(Cookie::class, $rememberMeCookie);
        $this->assertRememberMeCookieAttributes($client, $rememberMeCookie, 'REMEMBERME');
        self::assertNull($client->getCookieJar()->get('ADMIN_REMEMBERME'));

        return $rememberMeCookie;
    }

    private function issueAdminRememberMeCookie(KernelBrowser $client): Cookie
    {
        $client->request('GET', 'https://localhost/ru/admin/login');
        $client->submitForm('Войти', [
            'email' => UserFixtures::USER_ADMIN_1_EMAIL,
            'password' => 'test2test2',
            '_remember_me' => true,
        ]);

        self::assertResponseRedirects('/ru/admin/dashboard', Response::HTTP_FOUND);

        $rememberMeCookie = $client->getCookieJar()->get('ADMIN_REMEMBERME');
        self::assertInstanceOf(Cookie::class, $rememberMeCookie);
        $this->assertRememberMeCookieAttributes($client, $rememberMeCookie, 'ADMIN_REMEMBERME');
        self::assertNull($client->getCookieJar()->get('REMEMBERME'));

        return $rememberMeCookie;
    }

    /**
     * @return array{Cookie, Cookie}
     */
    private function issueBothRememberMeCookies(KernelBrowser $client): array
    {
        $frontCookie = $this->issueFrontRememberMeCookie($client);

        $client->getCookieJar()->clear();
        $adminCookie = $this->issueAdminRememberMeCookie($client);

        return [$frontCookie, $adminCookie];
    }

    private function assertBothRememberMeCookiesAreCleared(KernelBrowser $client): void
    {
        foreach (['REMEMBERME', 'ADMIN_REMEMBERME'] as $name) {
            $deletedCookies = array_filter(
                $client->getResponse()->headers->getCookies(),
                static fn ($cookie): bool => $name === $cookie->getName() && '/' === $cookie->getPath(),
            );

            self::assertNotEmpty($deletedCookies);
            foreach ($deletedCookies as $deletedCookie) {
                self::assertTrue($deletedCookie->isCleared());
                self::assertStringContainsString('Max-Age=0', (string) $deletedCookie);
            }
            self::assertNull($client->getCookieJar()->get($name));
        }
    }

    private function assertRememberMeCookieAttributes(KernelBrowser $client, Cookie $browserCookie, string $name): void
    {
        self::assertSame($name, $browserCookie->getName());
        self::assertSame('/', $browserCookie->getPath());
        self::assertTrue($browserCookie->isSecure());
        self::assertTrue($browserCookie->isHttpOnly());
        self::assertSame('lax', $browserCookie->getSameSite());

        $responseCookies = array_values(array_filter(
            $client->getResponse()->headers->getCookies(),
            static fn ($cookie): bool => $name === $cookie->getName(),
        ));
        self::assertCount(1, $responseCookies);
        $responseCookie = $responseCookies[0];
        self::assertSame('/', $responseCookie->getPath());
        self::assertTrue($responseCookie->isSecure());
        self::assertTrue($responseCookie->isHttpOnly());
        self::assertSame('lax', $responseCookie->getSameSite());
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
