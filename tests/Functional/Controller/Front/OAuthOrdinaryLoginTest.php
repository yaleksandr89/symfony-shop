<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Front;

use App\Entity\User;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Tests\TestUtils\OAuth\FakeOAuth2Client;
use App\Utils\Oauth2\Facebook\FacebookUser;
use App\Utils\Oauth2\Linkedin\LinkedinUser;
use App\Utils\Oauth2\Vk\VkUser;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

#[Group(name: 'functional')]
final class OAuthOrdinaryLoginTest extends WebTestCase
{
    #[DataProvider('providerCallbacks')]
    public function testLinkedIdentityLogsIntoSameUserWithoutEmailLookupMutationOrRegistration(
        OAuthProvider $provider,
        string $startPath,
        string $callbackPath,
    ): void {
        $nonce = str_replace('.', '', uniqid('', true));
        $externalId = OAuthProvider::Linkedin === $provider ? 'LiNkEdIn-Linked-Sub-'.$nonce : 'linked-'.$provider->value.'-'.$nonce;
        $localEmail = 'local-'.$provider->value.'-'.str_replace('.', '', uniqid('', true)).'@example.test';
        $providerEmail = 'provider-'.$provider->value.'-'.str_replace('.', '', uniqid('', true)).'@example.test';
        [$client, $fake] = $this->ordinaryClient($this->resourceOwner($provider, $externalId, $providerEmail));
        $user = $this->createUser($localEmail, $provider, $externalId);
        $beforePassword = $user->getPassword();
        $beforeCount = $this->userCount();

        $this->completeCallback($client, $startPath, $callbackPath);

        self::assertResponseRedirects('/ru/profile');
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame(1, $fake->userInfoRequests);
        $authenticatedUser = $client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        self::assertInstanceOf(User::class, $authenticatedUser);
        self::assertSame($user->getId(), $authenticatedUser->getId());
        self::assertSame($beforeCount, $this->userCount());
        $reloaded = $this->reload($user);
        self::assertSame($localEmail, $reloaded->getEmail());
        self::assertSame($externalId, $this->identity($reloaded, $provider));
        self::assertSame($beforePassword, $reloaded->getPassword());
        self::assertEmailCount(0);
    }

    #[DataProvider('optionalEmailProviders')]
    public function testExistingEmailWithoutIdentityIsDeniedGenericallyAndNeverLinked(
        OAuthProvider $provider,
        string $startPath,
        string $callbackPath,
    ): void
    {
        $email = 'collision-'.str_replace('.', '', uniqid('', true)).'@example.test';
        $externalId = OAuthProvider::Linkedin === $provider
            ? 'LiNkEdIn-Collision-Sub-'.str_replace('.', '', uniqid('', true))
            : 'collision-facebook-'.str_replace('.', '', uniqid('', true));
        [$client] = $this->ordinaryClient($this->resourceOwner($provider, $externalId, $email));
        $user = $this->createUser($email);
        $beforeCount = $this->userCount();

        $this->completeCallback($client, $startPath, $callbackPath);

        self::assertResponseRedirects('/ru/login');
        self::assertFalse($client->getContainer()->get('security.token_storage')->getToken()?->getUser() instanceof User);
        self::assertNull($this->identity($this->reload($user), $provider));
        self::assertSame($beforeCount, $this->userCount());
        self::assertEmailCount(0);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.alert-danger');
        self::assertSelectorTextContains('.alert-danger', 'Не удалось выполнить вход через социальную сеть.');
        self::assertStringNotContainsString($email, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString($externalId, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('fake-token', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('database detail', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('optionalEmailProviders')]
    public function testMissingEmailIsDeniedWithoutUserEmailOrLogin(
        OAuthProvider $provider,
        string $startPath,
        string $callbackPath,
    ): void
    {
        $externalId = OAuthProvider::Linkedin === $provider
            ? 'LiNkEdIn-Missing-Email-Sub-'.str_replace('.', '', uniqid('', true))
            : 'missing-email-facebook-'.str_replace('.', '', uniqid('', true));
        [$client] = $this->ordinaryClient($this->resourceOwner($provider, $externalId, null));
        $beforeCount = $this->userCount();

        $this->completeCallback($client, $startPath, $callbackPath);

        self::assertResponseRedirects('/ru/login');
        self::assertFalse($client->getContainer()->get('security.token_storage')->getToken()?->getUser() instanceof User);
        self::assertSame($beforeCount, $this->userCount());
        self::assertEmailCount(0);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.alert-danger');
        self::assertSelectorTextContains('.alert-danger', 'Не удалось выполнить вход через социальную сеть.');
        self::assertStringNotContainsString($externalId, (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('fake-token', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('optionalEmailProviders')]
    public function testNewUserIsPersistedUnverifiedBeforeStandardConfirmationEmailAndLogin(
        OAuthProvider $provider,
        string $startPath,
        string $callbackPath,
    ): void
    {
        $externalId = OAuthProvider::Linkedin === $provider
            ? 'LiNkEdIn-New-User-Sub-'.str_replace('.', '', uniqid('', true))
            : 'new-facebook-'.str_replace('.', '', uniqid('', true));
        $email = 'new-'.$provider->value.'-'.str_replace('.', '', uniqid('', true)).'@example.test';
        [$client] = $this->ordinaryClient($this->resourceOwner($provider, $externalId, $email));
        $beforeCount = $this->userCount();

        $this->completeCallback($client, $startPath, $callbackPath);

        self::assertResponseRedirects('/ru/profile');
        self::assertSame($beforeCount + 1, $this->userCount());
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getId());
        self::assertSame($externalId, $this->identity($user, $provider));
        self::assertFalse($user->isVerified(), 'The provider email_verified claim must not verify the local account.');
        self::assertNotSame('', trim($user->getPassword()));
        $authenticatedUser = $client->getContainer()->get('security.token_storage')->getToken()?->getUser();
        self::assertInstanceOf(User::class, $authenticatedUser);
        self::assertSame($user->getId(), $authenticatedUser->getId());

        self::assertEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertInstanceOf(RawMessage::class, $message);
        self::assertEmailAddressContains($message, 'to', $email);
        self::assertEmailSubjectContains($message, 'Please confirm your email');
        self::assertEmailHtmlBodyContains($message, 'Confirm my Email');
        self::assertEmailHtmlBodyContains($message, '/verify/email');
        self::assertEmailHtmlBodyNotContains($message, 'plainPassword');
        self::assertEmailHtmlBodyNotContains($message, 'new password');
    }

    /** @return iterable<string, array{OAuthProvider, string, string}> */
    public static function providerCallbacks(): iterable
    {
        yield 'Google' => [OAuthProvider::Google, '/ru/connect/google', '/ru/connect/google/check'];
        yield 'Yandex' => [OAuthProvider::Yandex, '/ru/connect/yandex', '/ru/connect/yandex/check'];
        yield 'Vkontakte' => [OAuthProvider::Vkontakte, '/ru/connect/vkontakte', '/ru/connect/vkontakte/check'];
        yield 'GitHub EN' => [OAuthProvider::GithubEn, '/ru/connect/github-en', '/ru/connect/github-en/check'];
        yield 'GitHub RU' => [OAuthProvider::GithubRus, '/ru/connect/github-ru', '/ru/connect/github-ru/check'];
        yield 'Facebook' => [OAuthProvider::Facebook, '/ru/connect/facebook', '/ru/connect/facebook/check'];
        yield 'LinkedIn' => [OAuthProvider::Linkedin, '/ru/connect/linkedin', '/ru/connect/linkedin/check'];
    }

    /** @return iterable<string, array{OAuthProvider, string, string}> */
    public static function optionalEmailProviders(): iterable
    {
        yield 'Facebook' => [OAuthProvider::Facebook, '/ru/connect/facebook', '/ru/connect/facebook/check'];
        yield 'LinkedIn' => [OAuthProvider::Linkedin, '/ru/connect/linkedin', '/ru/connect/linkedin/check'];
    }

    /** @return array{KernelBrowser, FakeOAuth2Client} */
    private function ordinaryClient(ResourceOwnerInterface $resourceOwner): array
    {
        $client = self::createClient();
        $client->disableReboot();
        $fake = new FakeOAuth2Client(self::getContainer()->get(RequestStack::class), $resourceOwner);
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
        $this->enableAllProviders();

        return [$client, $fake];
    }

    private function completeCallback(KernelBrowser $client, string $startPath, string $callbackPath): void
    {
        $client->request('GET', $startPath);
        self::assertResponseRedirects('https://provider.example/authorize?state=fake-oauth-state');

        $client->request('GET', $callbackPath, ['code' => 'fake-code', 'state' => 'fake-oauth-state']);
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

    private function resourceOwner(OAuthProvider $provider, string $externalId, ?string $email): ResourceOwnerInterface
    {
        return match ($provider) {
            OAuthProvider::Google => new GoogleUser([
                'sub' => $externalId,
                'email' => $email,
                'name' => 'Google User',
                'email_verified' => true,
            ]),
            OAuthProvider::Yandex => new YandexResourceOwner(array_filter([
                'id' => $externalId,
                'login' => 'yandex-user',
                'client_id' => 'client-id',
                'psuid' => 'psuid',
                'default_email' => $email,
            ], static fn (?string $value): bool => null !== $value)),
            OAuthProvider::Vkontakte => new VkUser([
                'user_id' => $externalId,
                'email' => $email,
                'first_name' => 'Vkontakte',
                'last_name' => 'User',
            ]),
            OAuthProvider::GithubEn, OAuthProvider::GithubRus => new GithubResourceOwner([
                'id' => $externalId,
                'email' => $email,
                'name' => 'GitHub User',
                'login' => 'github-user',
            ]),
            OAuthProvider::Facebook => new FacebookUser(array_filter([
                'id' => $externalId,
                'name' => 'Facebook User',
                'email' => $email,
            ], static fn (?string $value): bool => null !== $value)),
            OAuthProvider::Linkedin => new LinkedinUser(array_filter([
                'sub' => $externalId,
                'name' => 'LinkedIn User',
                'email' => $email,
                'email_verified' => true,
            ], static fn (mixed $value): bool => null !== $value)),
            default => throw new \LogicException('Unsupported test provider.'),
        };
    }

    private function createUser(string $email, ?OAuthProvider $provider = null, ?string $externalId = null): User
    {
        $user = (new User())->setEmail($email)->setIsVerified(true);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'known-password'));
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        $user->setFacebookId(null);
        $user->setLinkedinId(null);
        if (null !== $provider && null !== $externalId) {
            match ($provider) {
                OAuthProvider::Google => $user->setGoogleId($externalId),
                OAuthProvider::Yandex => $user->setYandexId($externalId),
                OAuthProvider::Vkontakte => $user->setVkontakteId($externalId),
                OAuthProvider::GithubEn, OAuthProvider::GithubRus => $user->setGithubId($externalId),
                OAuthProvider::Facebook => $user->setFacebookId($externalId),
                OAuthProvider::Linkedin => $user->setLinkedinId($externalId),
                default => throw new \LogicException('Unsupported test provider.'),
            };
        }
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    private function reload(User $user): User
    {
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $reloaded);

        return $reloaded;
    }

    private function userCount(): int
    {
        return (int) $this->entityManager()->getRepository(User::class)->count([]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
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
}
