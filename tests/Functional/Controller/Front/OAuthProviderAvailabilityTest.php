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

    #[DataProvider('locales')]
    public function testConfiguredEnabledFacebookRendersSocialWrapperAndOnlyFacebookButton(string $locale): void
    {
        foreach (['/login', '/registration'] as $path) {
            $client = $this->requestWithAvailability('/'.$locale.$path, [OAuthProvider::Facebook->value => true]);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertSelectorExists('.form-additional.mt-2.pt-1');
            self::assertSelectorCount(1, 'a[href="/'.$locale.'/connect/facebook"]');
            foreach (array_diff(self::START_PATHS, ['/connect/facebook']) as $oauthPath) {
                self::assertSelectorNotExists('a[href="/'.$locale.$oauthPath.'"]');
            }
        }
    }

    #[DataProvider('locales')]
    public function testConfiguredEnabledLinkedinRendersSocialWrapperAndOnlyLinkedinButton(string $locale): void
    {
        foreach (['/login', '/registration'] as $path) {
            $client = $this->requestWithAvailability('/'.$locale.$path, [OAuthProvider::Linkedin->value => true]);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertSelectorExists('.form-additional.mt-2.pt-1');
            self::assertSelectorCount(1, 'a[href="/'.$locale.'/connect/linkedin"]');
            self::assertSelectorTextContains('a[href="/'.$locale.'/connect/linkedin"]', 'ru' === $locale ? 'Авторизоваться через LinkedIn' : 'Sign in with LinkedIn');
            foreach (array_diff(self::START_PATHS, ['/connect/linkedin']) as $oauthPath) {
                self::assertSelectorNotExists('a[href="/'.$locale.$oauthPath.'"]');
            }
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
        $user->setFacebookId('already-linked-facebook-id');
        $user->setLinkedinId('Already-Linked-LinkedIn-Sub');
        $entityManager->flush();

        try {
            $client->loginUser($user, 'website');
            $client->request('GET', '/ru/profile');

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertSelectorNotExists('a[href="/ru/connect/facebook"]');
            self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/facebook/unlink"]');
            self::assertSelectorTextContains('a[href="/ru/profile/oauth/facebook/unlink"]', 'Отвязать');
            self::assertSelectorNotExists('a[href="/ru/profile/oauth/facebook/link"]');
            self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/linkedin/unlink"]');
            self::assertSelectorTextContains('a[href="/ru/profile/oauth/linkedin/unlink"]', 'Отвязать');
            self::assertSelectorNotExists('a[href="/ru/profile/oauth/linkedin/link"]');
        } finally {
            $user->setFacebookId(null);
            $user->setLinkedinId(null);
            $entityManager->flush();
        }
    }

    public function testDisabledUnlinkedLinkedinProfileHidesLinkAndUnlinkActions(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getLinkedinId());
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/ru/profile/oauth/linkedin/link"]');
        self::assertSelectorNotExists('a[href="/ru/profile/oauth/linkedin/unlink"]');
    }

    public function testEnabledUnlinkedLinkedinProfileRendersOnlyLinkAction(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Linkedin->value => true],
            [OAuthProvider::Linkedin->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getLinkedinId());
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/linkedin/link"]');
        self::assertSelectorTextContains('a[href="/ru/profile/oauth/linkedin/link"]', 'Привязать');
        self::assertSelectorNotExists('a[href="/ru/profile/oauth/linkedin/unlink"]');
    }

    public function testEnabledLinkedProfileProviderRendersOnlyUnlinkAction(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Facebook->value => true],
            [OAuthProvider::Facebook->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $user->setFacebookId('already-linked-facebook-id');
        $entityManager->flush();

        try {
            $client->loginUser($user, 'website');
            $client->request('GET', '/ru/profile');

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/facebook/unlink"]');
            self::assertSelectorTextContains('a[href="/ru/profile/oauth/facebook/unlink"]', 'Отвязать');
            self::assertSelectorNotExists('a[href="/ru/profile/oauth/facebook/link"]');
        } finally {
            $user->setFacebookId(null);
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

    public function testConfiguredEnabledFacebookReachesVersionedStartFlowWithoutNetworkRequest(): void
    {
        $client = self::createClient();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Facebook->value => true],
            [OAuthProvider::Facebook->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));

        $client->request('GET', '/ru/connect/facebook');

        self::assertResponseStatusCodeSame(Response::HTTP_FOUND);
        self::assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testEnabledUnlinkedFacebookProfileUsesExplicitConfirmationWhileLoginUsesPublicStart(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Facebook->value => true],
            [OAuthProvider::Facebook->value => ['clientId' => 'test-client-id', 'clientSecret' => 'test-client-secret']]
        ));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user, 'website');

        $client->request('GET', '/ru/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'a[href="/ru/profile/oauth/facebook/link"]');
        self::assertSelectorTextContains('a[href="/ru/profile/oauth/facebook/link"]', 'Привязать');
        self::assertSelectorNotExists('a[href="/ru/profile/oauth/facebook/unlink"]');
        self::assertSelectorNotExists('a[href="/ru/connect/facebook"]');

        $client->request('GET', '/ru/logout');
        self::assertTrue($client->getResponse()->isRedirect());
        $client->followRedirect();
        $client->request('GET', '/ru/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/ru/connect/facebook"]');
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
        '/connect/facebook',
        '/connect/linkedin',
        '/connect/google',
        '/connect/yandex',
        '/connect/vkontakte',
        '/connect/github-en',
        '/connect/github-ru',
    ];

    /** @var list<string> */
    private const CALLBACK_PATHS = [
        '/connect/facebook/check',
        '/connect/linkedin/check',
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
