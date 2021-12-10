<?php

namespace App\Tests\Functional\Controller\Main;

use Generator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group functional
 */
class DefaultControllerTest extends WebTestCase
{
    public function testRedirectEmptyUrlToLocale(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects(
            'http://localhost/en',
            Response::HTTP_MOVED_PERMANENTLY,
            sprintf('The %s URL redirections to the version with locale', '/')
        );
    }

    /**
     * @param string $url
     * @return void
     *
     * @dataProvider getPublicUrls
     */
    public function testPublicUrl(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseIsSuccessful(
            sprintf('The %s public URL loads correctly', $url)
        );
    }

    /**
     * @param string $url
     * @return void
     *
     * @dataProvider getSecureUrls
     */
    public function testSecureUrl(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        self::assertResponseRedirects(
            '/en/login',
            Response::HTTP_FOUND,
            sprintf('The %s URL redirections to the login page', $url)
        );
    }

    public function getPublicUrls(): ?Generator
    {
        yield ['/en/'];
        yield ['/en/login'];
        yield ['/en/registration'];
        yield ['/en/reset-password'];
    }

    public function getSecureUrls(): ?Generator
    {
        yield ['/en/profile'];
        yield ['/en/profile/edit'];
    }
}
