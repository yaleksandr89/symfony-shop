<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class LinkedinOAuthWiringTest extends WebTestCase
{
    #[DataProvider('locales')]
    #[TestDox('Настроенный старт LinkedIn соответствует контракту авторизации OIDC')]
    public function testConfiguredStartUsesLinkedinOidcAuthorizationContract(string $locale): void
    {
        $client = self::createClient();
        self::getContainer()->set(OAuthProviderAvailability::class, $this->availability(
            'test-linkedin-client-id',
            'test-linkedin-client-secret',
        ));

        $client->request('GET', '/'.$locale.'/connect/linkedin');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertSame('https', parse_url($location, PHP_URL_SCHEME));
        self::assertSame('www.linkedin.com', parse_url($location, PHP_URL_HOST));
        self::assertSame('/oauth/v2/authorization', parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('test-linkedin-client-id', $query['client_id'] ?? null);
        self::assertSame('openid profile email', $query['scope'] ?? null);
        self::assertSame('/'.$locale.'/connect/linkedin/check', parse_url((string) ($query['redirect_uri'] ?? ''), PHP_URL_PATH));
        self::assertIsString($query['state'] ?? null);
        self::assertNotSame('', trim((string) $query['state']));
    }

    #[TestDox('Провайдер без учётных данных возвращает безопасную серверную ошибку')]
    public function testEnabledProviderWithMissingCredentialsReturnsSanitizedServerError(): void
    {
        $client = self::createClient(['debug' => false]);
        $secret = 'linkedin-secret-not-for-output';
        self::getContainer()->set(OAuthProviderAvailability::class, $this->availability('', $secret));

        $client->request('GET', '/ru/connect/linkedin');

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertFalse($client->getResponse()->isRedirect());
        self::assertStringNotContainsString($secret, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('OAUTH_LINKEDIN_CLIENT_SECRET', (string) $client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'Russian' => ['ru'];
        yield 'English' => ['en'];
    }

    private function availability(string $clientId, string $clientSecret): OAuthProviderAvailability
    {
        return new OAuthProviderAvailability(
            [OAuthProvider::Linkedin->value => true],
            [OAuthProvider::Linkedin->value => ['clientId' => $clientId, 'clientSecret' => $clientSecret]],
        );
    }
}
