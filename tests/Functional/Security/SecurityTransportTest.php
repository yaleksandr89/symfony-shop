<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\ApiPlatform\ResourceTestUtils;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class SecurityTransportTest extends ResourceTestUtils
{
    #[DataProvider('acceptHeaders')]
    public function testAnonymousApiUsesProblemDetailsForEveryAccept(?string $accept): void
    {
        $client = self::createClient();
        $server = null === $accept ? [] : ['HTTP_ACCEPT' => $accept];

        $client->request('GET', '/api/orders', [], [], $server);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        $this->assertWarningFlash($client, []);
        self::assertFalse($client->getResponse()->headers->has('vary'));
    }

    public function testFullyAuthenticatedForbiddenApiUsesProblemDetailsWithoutFlash(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_1_EMAIL), 'website');

        $client->request('GET', '/api/orders', [], [], ['HTTP_ACCEPT' => 'text/plain']);

        $this->assertSecurityProblem($client, Response::HTTP_FORBIDDEN);
        $this->assertWarningFlash($client, []);
    }

    public function testRememberMeOnlyAuthenticationIsInsufficientForAdminApiOperation(): void
    {
        $client = self::createClient();
        $rememberMeCookie = $this->issueFrontRememberMeCookie($client);

        $client->getCookieJar()->clear();
        $client->getCookieJar()->set($rememberMeCookie);
        self::assertNull($client->getCookieJar()->get('MOCKSESSID'));
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));

        $client->request('GET', '/api/orders', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertSecurityProblem($client, Response::HTTP_UNAUTHORIZED);
        $this->assertWarningFlash($client, []);
    }

    public function testFullAdminSessionAuthenticationIsPreservedForApi(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $client->request('GET', '/api/orders', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testFullyAuthorizedProtectedApiKeepsUnsupportedAcceptResponse(): void
    {
        $client = self::createClient();
        $client->loginUser($this->getUser(UserFixtures::USER_ADMIN_1_EMAIL), 'website');

        $client->request('GET', '/api/orders', [], [], ['HTTP_ACCEPT' => 'text/plain']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_ACCEPTABLE);
        self::assertFalse($client->getResponse()->headers->has('www-authenticate'));
        self::assertFalse($client->getResponse()->headers->has('location'));
    }

    public function testAnonymousFrontBrowserRequestKeepsRedirectAndWarningFlash(): void
    {
        $client = self::createClient();

        $client->request('GET', '/ru/profile');

        self::assertResponseRedirects('/ru/login', Response::HTTP_FOUND);
        self::assertNotSame('application/problem+json', $client->getResponse()->headers->get('content-type'));
        $this->assertWarningFlash($client, ['You have to login in order to access this page.']);
    }

    public function testAnonymousAdminBrowserRequestKeepsRedirectAndWarningFlash(): void
    {
        $client = self::createClient();

        $client->request('GET', '/ru/admin/dashboard');

        self::assertResponseRedirects('/ru/admin/login', Response::HTTP_FOUND);
        self::assertNotSame('application/problem+json', $client->getResponse()->headers->get('content-type'));
        $this->assertWarningFlash($client, ['You have to login in order to access this page.']);
    }

    public function testPublicApiKeepsUnsupportedAcceptResponse(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/products', [], [], ['HTTP_ACCEPT' => 'text/plain']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_ACCEPTABLE);
        self::assertFalse($client->getResponse()->headers->has('www-authenticate'));
        self::assertFalse($client->getResponse()->headers->has('location'));
    }

    /** @return iterable<string, array{string|null}> */
    public static function acceptHeaders(): iterable
    {
        yield 'problem json' => ['application/problem+json'];
        yield 'json' => ['application/json'];
        yield 'json ld' => ['application/ld+json'];
        yield 'absent' => [null];
        yield 'unsupported' => ['text/plain'];
    }

    private function issueFrontRememberMeCookie(KernelBrowser $client): Cookie
    {
        $client->request('GET', '/ru/login');
        $client->submitForm('Авторизоваться', [
            'email' => UserFixtures::USER_1_EMAIL,
            'password' => 'test3test3',
            '_remember_me' => true,
        ]);

        self::assertResponseRedirects('/ru/profile', Response::HTTP_FOUND);
        $rememberMeCookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertInstanceOf(Cookie::class, $rememberMeCookie);

        return $rememberMeCookie;
    }

    /** @param list<string> $expected */
    private function assertWarningFlash(KernelBrowser $client, array $expected): void
    {
        $request = $client->getRequest();
        self::assertInstanceOf(Request::class, $request);
        self::assertSame($expected, $request->getSession()->getFlashBag()->peek('warning'));
    }

    private function getUser(string $email): User
    {
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
