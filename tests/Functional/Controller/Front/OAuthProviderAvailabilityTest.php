<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'functional')]
final class OAuthProviderAvailabilityTest extends WebTestCase
{
    #[DataProvider('locales')]
    public function testLoginAndRegistrationHideDisabledCurrentProviders(string $locale): void
    {
        $client = self::createClient();

        foreach (['/login', '/registration'] as $path) {
            $client->request('GET', '/'.$locale.$path);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            foreach (self::START_PATHS as $oauthPath) {
                self::assertStringNotContainsString('href="/'.$locale.$oauthPath.'"', (string) $client->getResponse()->getContent());
            }

            if ('/login' === $path) {
                self::assertSelectorCount(1, '.form-additional.mt-2.pt-1');
            } else {
                self::assertSelectorNotExists('.form-additional.mt-2.pt-1');
            }
        }
    }

    public function testConfiguredEnabledYandexRendersSocialWrapperAndOnlyYandexButton(): void
    {
        foreach (['/ru/login', '/ru/registration'] as $path) {
            $client = $this->requestWithAvailability($path, [OAuthProvider::Yandex->value => true]);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertSelectorExists('.form-additional.mt-2.pt-1');
            self::assertSelectorExists('a[href="/ru/connect/yandex"]');
            self::assertSelectorNotExists('a[href="/ru/connect/google"]');
            self::assertSelectorNotExists('a[href="/ru/connect/vkontakte"]');
            self::assertSelectorNotExists('a[href="/ru/connect/github-ru"]');
            self::assertSelectorNotExists('a[href="/ru/connect/github-en"]');
        }
    }

    public function testDisabledRoutesAreNotFoundWithoutRedirectOrUserMutation(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $before = (int) $entityManager->getRepository(User::class)->count([]);

        foreach (array_merge(self::START_PATHS, self::CALLBACK_PATHS) as $oauthPath) {
            $client->request('GET', '/ru'.$oauthPath, ['code' => 'must-not-be-exchanged', 'state' => 'must-not-be-used']);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            self::assertFalse($client->getResponse()->isRedirect());
        }

        self::assertSame($before, (int) $entityManager->getRepository(User::class)->count([]));
    }

    public function testProfileHidesDisabledConnectButtonsButKeepsLinkedIdentityUnlinkAction(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $user->setGoogleId('already-linked-google-id');
        $entityManager->flush();

        try {
            $client->loginUser($user, 'website');
            $client->request('GET', '/ru/profile');

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertSelectorNotExists('a[href="/ru/connect/yandex"]');
            self::assertSelectorNotExists('a[href="/ru/connect/vkontakte"]');
            self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/google/unlink"]');
            self::assertSelectorTextContains('a[href="/ru/profile/oauth/google/unlink"]', 'Отвязать');
            self::assertSelectorNotExists('a[href="/ru/profile/oauth/google/link"]');
        } finally {
            $user->setGoogleId(null);
            $entityManager->flush();
        }
    }

    public function testEnabledLinkedProfileProviderRendersOnlyUnlinkAction(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Google->value => true],
            [OAuthProvider::Google->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $user->setGoogleId('already-linked-google-id');
        $entityManager->flush();

        try {
            $client->loginUser($user, 'website');
            $client->request('GET', '/ru/profile');

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/google/unlink"]');
            self::assertSelectorTextContains('a[href="/ru/profile/oauth/google/unlink"]', 'Отвязать');
            self::assertSelectorNotExists('a[href="/ru/profile/oauth/google/link"]');
        } finally {
            $user->setGoogleId(null);
            $entityManager->flush();
        }
    }

    public function testConfiguredEnabledYandexReachesExistingStartFlowWithoutNetworkRequest(): void
    {
        $client = self::createClient();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Yandex->value => true],
            [OAuthProvider::Yandex->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));

        $client->request('GET', '/ru/connect/yandex');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertTrue($client->getResponse()->isRedirect());
    }

    public function testEnabledUnlinkedProfileProviderUsesExplicitConfirmationWhileLoginUsesPublicStart(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Yandex->value => true],
            [OAuthProvider::Yandex->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/yandex/link"]');
        self::assertSelectorTextContains('a[href="/ru/profile/oauth/yandex/link"]', 'Привязать');
        self::assertSelectorNotExists('a[href="/ru/profile/oauth/yandex/unlink"]');
        self::assertSelectorNotExists('a[href="/ru/connect/yandex"]');

        $client->request('GET', '/ru/logout');
        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $client->request('GET', '/ru/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/ru/connect/yandex"]');
    }

    public function testEnabledProviderWithMissingCredentialsReturnsSanitizedServerError(): void
    {
        $client = self::createClient(['debug' => false]);
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Yandex->value => true],
            [OAuthProvider::Yandex->value => ['clientId' => '', 'clientSecret' => '']]
        ));

        $client->request('GET', '/ru/connect/yandex');

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertStringNotContainsString('OAUTH_YANDEX_CLIENT_SECRET', (string) $client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        yield 'Russian' => ['ru'];
        yield 'English' => ['en'];
    }

    /** @var list<string> */
    private const START_PATHS = [
        '/connect/google',
        '/connect/yandex',
        '/connect/vkontakte',
        '/connect/github-en',
        '/connect/github-ru',
    ];

    /** @var list<string> */
    private const CALLBACK_PATHS = [
        '/connect/google/check',
        '/connect/yandex/check',
        '/connect/vkontakte/check',
        '/connect/github-en/check',
        '/connect/github-ru/check',
    ];

    /** @param array<string, bool> $enabled */
    private function requestWithAvailability(string $path, array $enabled): KernelBrowser
    {
        self::ensureKernelShutdown();
        $client = self::createClient();
        $credentials = [];
        foreach (OAuthProvider::cases() as $provider) {
            if ($provider->isImplemented()) {
                $credentials[$provider->value] = [
                    'clientId' => 'test-'.$provider->value.'-id',
                    'clientSecret' => 'test-'.$provider->value.'-secret',
                ];
            }
        }
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability($enabled, $credentials));
        $client->request('GET', $path);

        return $client;
    }
}
