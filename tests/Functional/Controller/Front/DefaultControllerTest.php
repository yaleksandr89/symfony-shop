<?php

namespace App\Tests\Functional\Controller\Front;

use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
class DefaultControllerTest extends WebTestCase
{
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

    #[DataProvider(methodName: 'getPublicUrls')]
    public function testPublicUrl(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful(
            sprintf('The %s public URL loads correctly', $url)
        );
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

    public static function getPublicUrls(): ?Generator
    {
        yield ['/ru/'];
        yield ['/ru/login'];
        yield ['/ru/registration'];
        yield ['/ru/reset-password'];
    }

    public static function getSecureUrls(): ?Generator
    {
        yield ['/ru/profile'];
        yield ['/ru/profile/edit'];
    }
}
