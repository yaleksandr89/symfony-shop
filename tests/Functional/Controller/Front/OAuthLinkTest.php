<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Tests\TestUtils\OAuth\FakeOAuth2Client;
use App\Tests\TestUtils\OAuth\FakeOAuthResourceOwner;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group(name: 'functional')]
final class OAuthLinkTest extends WebTestCase
{
    private const PASSWORD = 'current-password';

    #[TestDox('Неаутентифицированные запросы подтверждения отклоняются')]
    public function testUnauthenticatedConfirmationRequestsAreDenied(): void
    {
        $client = self::createClient();

        $client->request('GET', '/ru/profile/oauth/google/link');
        self::assertResponseRedirects('/ru/login');

        $client->request('POST', '/ru/profile/oauth/google/link', [
            'oauth_link_form' => ['currentPassword' => self::PASSWORD],
        ]);
        self::assertResponseRedirects('/ru/login');
    }

    #[TestDox('Отключённый, связанный в будущем и неизвестный провайдеры отклоняются до вызова клиента')]
    public function testDisabledLinkedFutureAndUnknownProvidersFailBeforeClientAccess(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $user = $this->createUser(['google' => 'linked-google']);
        $client->loginUser($user, 'website');
        $fake = $this->installFakeClients('external-id');

        foreach (['yandex', 'google', 'linkedin', 'unknown'] as $provider) {
            $client->request('GET', '/ru/profile/oauth/'.$provider.'/link');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }

        self::assertSame(0, $fake->redirectCalls);
        self::assertSame(0, $fake->registryAccesses);
        self::assertSame(0, $fake->tokenRequests);
    }

    #[TestDox('Провайдер без учётных данных очищается до вызова клиента')]
    public function testEnabledProviderWithMissingCredentialsIsSanitizedBeforeClientAccess(): void
    {
        $client = self::createClient(['debug' => false]);
        $client->disableReboot();
        $user = $this->createUser();
        $client->loginUser($user, 'website');
        $fake = $this->installFakeClients('external-id');
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability(
            [OAuthProvider::Google->value => true],
            [OAuthProvider::Google->value => ['clientId' => '', 'clientSecret' => 'not-for-output']]
        ));

        $client->request('GET', '/ru/profile/oauth/google/link');

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
        self::assertStringNotContainsString('not-for-output', (string) $client->getResponse()->getContent());
        self::assertSame(0, $fake->redirectCalls);
        self::assertSame(0, $fake->registryAccesses);
    }

    #[TestDox('GET показывает форму подтверждения пароля, не создавая намерение')]
    public function testGetRendersPasswordConfirmationWithoutCreatingIntent(): void
    {
        [$client, $user, $fake] = $this->linkClient(OAuthProvider::Google, 'external-id');

        $client->request('GET', '/ru/profile/oauth/google/link');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[method="post"]');
        self::assertSelectorExists('input[name="oauth_link_form[_token]"]');
        self::assertSelectorExists('input[name="oauth_link_form[currentPassword]"][type="password"]');
        self::assertStringNotContainsString('external-id', (string) $client->getResponse()->getContent());
        self::assertFalse($client->getRequest()->getSession()->has('oauth_link_intent'));
        self::assertSame(0, $fake->redirectCalls);
        self::assertSame(0, $fake->registryAccesses);
        self::assertNull($user->getGoogleId());
    }

    #[DataProvider('invalidConfirmationCases')]
    #[TestDox('Некорректное подтверждение не создаёт намерение и не перенаправляет к провайдеру')]
    public function testInvalidConfirmationCreatesNoIntentOrProviderRedirect(string $case): void
    {
        [$client, , $fake] = $this->linkClient(OAuthProvider::Google, 'external-id');
        $crawler = $client->request('GET', '/ru/profile/oauth/google/link');

        if ('missing-csrf' === $case) {
            $client->request('POST', '/ru/profile/oauth/google/link', [
                'oauth_link_form' => ['currentPassword' => self::PASSWORD],
            ]);
        } else {
            $form = $crawler->filter('form')->form([
                'oauth_link_form[currentPassword]' => 'blank-password' === $case ? '' : 'wrong-password',
            ]);
            $client->submit($form);
        }

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertFalse($client->getRequest()->getSession()->has('oauth_link_intent'));
        self::assertSame(0, $fake->redirectCalls);
        self::assertSame(0, $fake->registryAccesses);
        self::assertSame(0, $fake->tokenRequests);
    }

    #[TestDox('Корректное подтверждение создаёт связанное хешированное намерение без изменений')]
    public function testValidConfirmationCreatesBoundHashedIntentWithoutMutation(): void
    {
        [$client, $user, $fake] = $this->linkClient(OAuthProvider::Google, 'external-id');
        $beforeCount = $this->userCount();
        $crawler = $client->request('GET', '/ru/profile/oauth/google/link');
        $form = $crawler->filter('form')->form([
            'oauth_link_form[currentPassword]' => self::PASSWORD,
        ]);

        $client->submit($form);

        self::assertResponseRedirects('https://provider.example/authorize?state=fake-oauth-state');
        self::assertSame(1, $fake->redirectCalls);
        self::assertSame(1, $fake->registryAccesses);
        $rawIntent = $client->getRequest()->getSession()->get('oauth_link_intent');
        self::assertIsArray($rawIntent);
        self::assertSame($user->getId(), $rawIntent['userId']);
        self::assertSame('google', $rawIntent['provider']);
        self::assertSame(hash('sha256', 'fake-oauth-state'), $rawIntent['stateHash']);
        self::assertIsInt($rawIntent['issuedAt']);
        self::assertStringNotContainsString('fake-oauth-state', serialize($rawIntent));
        self::assertNull($this->reload($user)->getGoogleId());
        self::assertSame($beforeCount, $this->userCount());
        self::assertEmailCount(0);
    }

    #[TestDox('Обычный старт и прямой callback аутентифицированного пользователя отклоняются до вызова клиента')]
    public function testAuthenticatedOrdinaryStartAndDirectCallbackAreDeniedBeforeClientAccess(): void
    {
        [$client, , $fake] = $this->linkClient(OAuthProvider::Yandex, 'external-id');

        $client->request('GET', '/ru/connect/yandex');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $fake->redirectCalls);
        self::assertSame(0, $fake->registryAccesses);

        $client->request('GET', '/ru/connect/yandex/check', ['code' => 'code', 'state' => 'state']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(0, $fake->tokenRequests);
        self::assertSame(0, $fake->registryAccesses);
    }

    #[TestDox('Неверное состояние и несовпавший провайдер погашают намерение до вызова клиента')]
    public function testWrongStateAndProviderMismatchConsumeIntentBeforeClientAccess(): void
    {
        foreach ([
            ['callback' => '/ru/connect/google/check', 'state' => 'wrong-state'],
            ['callback' => '/ru/connect/yandex/check', 'state' => 'fake-oauth-state'],
        ] as $case) {
            self::ensureKernelShutdown();
            [$client, , $fake] = $this->linkClient(OAuthProvider::Google, 'external-id');
            $this->startLink($client, OAuthProvider::Google);

            $client->request('GET', $case['callback'], ['code' => 'code', 'state' => $case['state']]);

            self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
            self::assertFalse($client->getRequest()->getSession()->has('oauth_link_intent'));
            self::assertFalse($client->getRequest()->getSession()->has('knpu.oauth2_client_state'));
            self::assertSame(0, $fake->tokenRequests);
            self::assertSame(1, $fake->registryAccesses);
        }
    }

    #[DataProvider('invalidCallbackCases')]
    #[TestDox('Отсутствующее, массивное, истёкшее и чужое состояние отклоняются до вызова клиента')]
    public function testMissingArrayExpiredAndWrongUserStateAreDeniedBeforeClientAccess(string $case): void
    {
        [$client, , $fake] = $this->linkClient(OAuthProvider::Google, 'external-id');
        $this->startLink($client, OAuthProvider::Google);
        if ('expired' === $case) {
            $intent = $client->getRequest()->getSession()->get('oauth_link_intent');
            self::assertIsArray($intent);
            $intent['issuedAt'] = 0;
            $client->getRequest()->getSession()->set('oauth_link_intent', $intent);
            $client->getRequest()->getSession()->save();
        } elseif ('wrong-user' === $case) {
            $intent = $client->getRequest()->getSession()->get('oauth_link_intent');
            self::assertIsArray($intent);
            $intent['userId'] = $this->createUser()->getId();
            $client->getRequest()->getSession()->set('oauth_link_intent', $intent);
            $client->getRequest()->getSession()->save();
        }

        $query = ['code' => 'code'];
        if ('missing' !== $case) {
            $query['state'] = 'array' === $case ? ['fake-oauth-state'] : 'fake-oauth-state';
        }
        $client->request('GET', '/ru/connect/google/check', $query);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertFalse($client->getRequest()->getSession()->has('oauth_link_intent'));
        self::assertFalse($client->getRequest()->getSession()->has('knpu.oauth2_client_state'));
        self::assertSame(0, $fake->tokenRequests);
        self::assertSame(1, $fake->registryAccesses);
    }

    #[DataProvider('providerCallbacks')]
    #[TestDox('Успешный callback связывает текущего пользователя, а повтор отклоняется до обмена')]
    public function testSuccessfulCallbackLinksCurrentUserAndReplayFailsBeforeExchange(
        OAuthProvider $provider,
        string $callbackPath,
    ): void {
        $nonce = str_replace('.', '', uniqid('', true));
        $externalId = OAuthProvider::Linkedin === $provider ? 'LiNkEdIn-Link-Sub-'.$nonce : 'linked-'.$provider->value.'-'.$nonce;
        [$client, $user, $fake] = $this->linkClient($provider, $externalId);
        $beforeCount = $this->userCount();
        $beforeEmail = $user->getEmail();
        $beforeIdentities = $this->identities($user);
        $this->startLink($client, $provider);

        $client->request('GET', $callbackPath, ['code' => 'fake-code', 'state' => 'fake-oauth-state']);

        self::assertResponseRedirects('/ru/profile');
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame(1, $fake->userInfoRequests);
        self::assertSame(2, $fake->registryAccesses);
        self::assertFalse($client->getRequest()->getSession()->has('oauth_link_intent'));
        self::assertFalse($client->getRequest()->getSession()->has('knpu.oauth2_client_state'));
        $reloaded = $this->reload($user);
        $expectedIdentities = $beforeIdentities;
        $expectedIdentities[$provider->identityFamily()] = $externalId;
        self::assertSame($expectedIdentities, $this->identities($reloaded));
        self::assertSame($beforeEmail, $reloaded->getEmail());
        self::assertSame($user->getId(), $client->getContainer()->get('security.token_storage')->getToken()?->getUser()?->getId());
        self::assertSame($beforeCount, $this->userCount());
        self::assertEmailCount(0);

        $client->request('GET', $callbackPath, ['code' => 'fake-code', 'state' => 'fake-oauth-state']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame(2, $fake->registryAccesses);
        $afterReplay = $this->reload($user);
        self::assertSame($expectedIdentities, $this->identities($afterReplay));
        self::assertSame($beforeEmail, $afterReplay->getEmail());
        self::assertSame($beforeCount, $this->userCount());
    }

    #[TestDox('Чужой внешний идентификатор даёт нейтральную ошибку без изменения текущего пользователя')]
    public function testOwnedExternalIdentityProducesGenericFailureWithoutCurrentUserMutation(): void
    {
        $externalId = 'owned-'.str_replace('.', '', uniqid('', true));
        [$client, $user, $fake] = $this->linkClient(OAuthProvider::Google, $externalId);
        $owner = $this->createUser(['google' => $externalId]);
        $beforeCount = $this->userCount();
        $this->startLink($client, OAuthProvider::Google);

        $client->request('GET', '/ru/connect/google/check', ['code' => 'fake-code', 'state' => 'fake-oauth-state']);

        self::assertResponseRedirects('/ru/profile');
        self::assertNull($this->reload($user)->getGoogleId());
        self::assertSame($externalId, $this->reload($owner)->getGoogleId());
        self::assertSame($beforeCount, $this->userCount());
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame($user->getId(), $client->getContainer()->get('security.token_storage')->getToken()?->getUser()?->getId());
        self::assertEmailCount(0);

        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Не удалось привязать аккаунт социальной сети.');
        self::assertStringNotContainsString($externalId, (string) $client->getResponse()->getContent());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidConfirmationCases(): iterable
    {
        yield 'missing CSRF' => ['missing-csrf'];
        yield 'blank password' => ['blank-password'];
        yield 'wrong password' => ['wrong-password'];
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCallbackCases(): iterable
    {
        yield 'missing state' => ['missing'];
        yield 'array state' => ['array'];
        yield 'expired intent' => ['expired'];
        yield 'wrong user' => ['wrong-user'];
    }

    /** @return iterable<string, array{OAuthProvider, string}> */
    public static function providerCallbacks(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, '/ru/connect/google/check'];
        yield 'Yandex' => [OAuthProvider::Yandex, '/ru/connect/yandex/check'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, '/ru/connect/vkontakte/check'];
        yield 'GitHub EN' => [OAuthProvider::GithubEn, '/ru/connect/github-en/check'];
        yield 'GitHub RU' => [OAuthProvider::GithubRus, '/ru/connect/github-ru/check'];
        yield 'Facebook' => [OAuthProvider::Facebook, '/ru/connect/facebook/check'];
        yield 'LinkedIn' => [OAuthProvider::Linkedin, '/ru/connect/linkedin/check'];
    }

    /** @return array{KernelBrowser, User, FakeOAuth2Client} */
    private function linkClient(OAuthProvider $provider, string $externalId): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $user = $this->createUser();
        $client->loginUser($user, 'website');
        $fake = $this->installFakeClients($externalId);
        $this->enableAllProviders();

        return [$client, $user, $fake];
    }

    private function startLink(KernelBrowser $client, OAuthProvider $provider): void
    {
        $crawler = $client->request('GET', '/ru/profile/oauth/'.$provider->value.'/link');
        $form = $crawler->filter('form')->form([
            'oauth_link_form[currentPassword]' => self::PASSWORD,
        ]);
        $client->submit($form);
        self::assertResponseRedirects('https://provider.example/authorize?state=fake-oauth-state');
    }

    private function installFakeClients(string $externalId): FakeOAuth2Client
    {
        $fake = new FakeOAuth2Client(
            self::getContainer()->get(RequestStack::class),
            new FakeOAuthResourceOwner($externalId)
        );
        $container = new class($fake) extends Container {
            public function __construct(private readonly FakeOAuth2Client $fake)
            {
                parent::__construct();
            }

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                ++$this->fake->registryAccesses;

                return parent::get($id, $invalidBehavior);
            }
        };
        $container->set('fake.oauth', $fake);
        $serviceMap = [];
        foreach (OAuthProvider::cases() as $provider) {
            if ($provider->isImplemented()) {
                $serviceMap[$provider->oauthClientName()] = 'fake.oauth';
            }
        }
        self::getContainer()->set('knpu.oauth2.registry', new ClientRegistry($container, $serviceMap));

        return $fake;
    }

    private function enableAllProviders(): void
    {
        $enabled = [];
        $credentials = [];
        foreach (OAuthProvider::cases() as $provider) {
            if ($provider->isImplemented()) {
                $enabled[$provider->value] = true;
                $credentials[$provider->value] = [
                    'clientId' => 'test-'.$provider->value.'-id',
                    'clientSecret' => 'test-'.$provider->value.'-secret',
                ];
            }
        }
        self::getContainer()->set(OAuthProviderAvailability::class, new OAuthProviderAvailability($enabled, $credentials));
    }

    /** @param array<string, string> $identities */
    private function createUser(array $identities = []): User
    {
        $nonce = str_replace('.', '', uniqid('', true));
        $user = new User();
        $user->setEmail('oauth-link-'.$nonce.'@example.test')->setIsVerified(true);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $user->setGoogleId($identities['google'] ?? null);
        $user->setYandexId($identities['yandex'] ?? null);
        $user->setVkontakteId($identities['vkontakte'] ?? null);
        $user->setGithubId($identities['github'] ?? null);
        $user->setFacebookId($identities['facebook'] ?? null);
        $user->setLinkedinId($identities['linkedin'] ?? null);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function reload(User $user): User
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }

    private function userCount(): int
    {
        return (int) self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->count([]);
    }

    private function identity(User $user, OAuthProvider $provider): ?string
    {
        return match ($provider) {
            OAuthProvider::Google => $user->getGoogleId(),
            OAuthProvider::Yandex => $user->getYandexId(),
            OAuthProvider::Vkontakte => $user->getVkontakteId(),
            OAuthProvider::GithubEn, OAuthProvider::GithubRus => $user->getGithubId(),
            OAuthProvider::Facebook => $user->getFacebookId(),
            OAuthProvider::Linkedin => $user->getLinkedinId(),
            default => null,
        };
    }

    /** @return array<string, ?string> */
    private function identities(User $user): array
    {
        return [
            'google' => $user->getGoogleId(),
            'yandex' => $user->getYandexId(),
            'vkontakte' => $user->getVkontakteId(),
            'github' => $user->getGithubId(),
            'facebook' => $user->getFacebookId(),
            'linkedin' => $user->getLinkedinId(),
        ];
    }
}
