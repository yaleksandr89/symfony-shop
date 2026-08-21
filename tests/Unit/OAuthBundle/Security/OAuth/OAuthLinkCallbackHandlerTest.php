<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthLinkCallbackHandler;
use App\OAuthBundle\Security\OAuth\OAuthLinkIntentStore;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use App\Tests\TestUtils\OAuth\FakeOAuth2Client;
use App\Tests\TestUtils\OAuth\FakeOAuthResourceOwner;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group(name: 'unit')]
final class OAuthLinkCallbackHandlerTest extends TestCase
{
    #[TestDox('Аутентифицированный callback без намерения отклоняется до внешнего доступа')]
    public function testAuthenticatedCallbackWithoutIntentIsDeniedBeforeExternalAccess(): void
    {
        [$handler, $fake] = $this->handler(true, false);

        try {
            $handler->handle($this->request(), OAuthProvider::Google);
            self::fail('A callback without intent must be denied.');
        } catch (AccessDeniedHttpException) {
            self::assertSame(0, $fake->tokenRequests);
            self::assertSame(0, $fake->userInfoRequests);
            self::assertSame(0, $fake->registryAccesses);
        }
    }

    #[TestDox('Callback вышедшего пользователя с намерением отклоняется до внешнего доступа')]
    public function testLoggedOutCallbackWithIntentIsDeniedBeforeExternalAccess(): void
    {
        [$handler, $fake] = $this->handler(false, true);

        try {
            $handler->handle($this->request(), OAuthProvider::Google);
            self::fail('A link callback requires the same authenticated user.');
        } catch (AccessDeniedHttpException) {
            self::assertSame(0, $fake->tokenRequests);
            self::assertSame(0, $fake->userInfoRequests);
            self::assertSame(0, $fake->registryAccesses);
        }
    }

    #[TestDox('Корректный callback связывает без замены токена, повтор отклоняется')]
    public function testValidCallbackLinksWithoutReplacingTokenAndReplayIsDenied(): void
    {
        [$handler, $fake, $request, $user, $store, $tokenStorage] = $this->handler(true, true);

        $response = $handler->handle($request, OAuthProvider::Google);

        self::assertSame('/ru/profile', $response->headers->get('Location'));
        self::assertSame('external-id', $user->getGoogleId());
        self::assertSame($user, $tokenStorage->getToken()?->getUser());
        self::assertFalse($store->hasPending());
        self::assertFalse($request->getSession()->has(OAuth2Client::OAUTH2_SESSION_STATE_KEY));
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame(1, $fake->userInfoRequests);
        self::assertSame(1, $fake->registryAccesses);

        $this->expectException(AccessDeniedHttpException::class);
        try {
            $handler->handle($request, OAuthProvider::Google);
        } finally {
            self::assertSame(1, $fake->tokenRequests);
        }
    }

    #[TestDox('Сбой после погашения намерения безопасно возвращает в профиль без утечки деталей')]
    #[DataProvider('postConsumptionFailures')]
    public function testFailureAfterIntentConsumptionIsSanitizedAndDoesNotReplaceAuthentication(string $failurePhase): void
    {
        [$handler, $fake, $request, $user, $store, $tokenStorage] = $this->handler(true, true, $failurePhase);
        $token = $tokenStorage->getToken();
        $before = $this->identities($user);
        self::assertSame(
            $request->query->get('state'),
            $request->getSession()->get(OAuth2Client::OAUTH2_SESSION_STATE_KEY),
        );

        $response = $handler->handle($request, OAuthProvider::Google);

        self::assertSame('/ru/profile', $response->headers->get('Location'));
        self::assertSame([
            'danger' => ['personal_account.social_group.oauth_link.failure'],
        ], $request->getSession()->getFlashBag()->peekAll());
        self::assertStringNotContainsString('secret-upstream-detail', serialize($request->getSession()->getFlashBag()->peekAll()));
        self::assertFalse($request->getSession()->has(OAuth2Client::OAUTH2_SESSION_STATE_KEY));
        self::assertFalse($store->hasPending());
        self::assertSame($token, $tokenStorage->getToken());
        self::assertSame($user, $tokenStorage->getToken()?->getUser());
        self::assertSame($before, $this->identities($user));
        self::assertSame(1, $fake->tokenRequests);
        self::assertSame('external' === $failurePhase ? 0 : 1, $fake->userInfoRequests);
    }

    /** @return iterable<string, array{string}> */
    public static function postConsumptionFailures(): iterable
    {
        yield 'external token exchange' => ['external'];
        yield 'account link persistence' => ['link'];
    }

    /** @return array{OAuthLinkCallbackHandler, OAuth2ClientInterface, Request, User, OAuthLinkIntentStore, TokenStorage} */
    private function handler(bool $loggedIn, bool $pending, ?string $failurePhase = null): array
    {
        $request = $this->request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $store = new OAuthLinkIntentStore($requestStack, new MockClock('2026-08-06 12:00:00 UTC'));
        $user = new class() extends User {
            public function assignId(int $id): void
            {
                $this->id = $id;
            }
        };
        $user->assignId(13);
        $user->setGoogleId(null);
        $user->setYandexId(null);
        $user->setVkontakteId(null);
        $user->setGithubId(null);
        if ($pending) {
            $store->store($user, OAuthProvider::Google, 'fake-oauth-state');
        }

        $tokenStorage = new TokenStorage();
        if ($loggedIn) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'website', ['ROLE_USER']));
        }

        $fake = 'external' === $failurePhase
            ? new ThrowingOAuth2Client(new \RuntimeException('secret-upstream-detail'))
            : new FakeOAuth2Client($requestStack, new FakeOAuthResourceOwner('external-id'));
        $container = new class($fake) extends Container {
            public function __construct(private readonly OAuth2ClientInterface $fake)
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
        $registry = new ClientRegistry($container, ['google_main' => 'fake.oauth']);
        $request->getSession()->set(OAuth2Client::OAUTH2_SESSION_STATE_KEY, 'fake-oauth-state');
        $shouldLink = $loggedIn && $pending;
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($shouldLink && 'external' !== $failurePhase ? self::once() : self::never())
            ->method('findOneBy')
            ->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $flush = $entityManager->expects($shouldLink && 'external' !== $failurePhase ? self::once() : self::never())
            ->method('flush');
        if ('link' === $failurePhase) {
            $flush->willThrowException(new \RuntimeException('secret-upstream-detail'));
        }
        $linker = new OAuthAccountLinker(new OAuthIdentityAccessor(), $repository, $entityManager);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($shouldLink ? self::once() : self::never())->method('generate')->willReturn('/ru/profile');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($shouldLink ? self::once() : self::never())->method('trans')->willReturnArgument(0);

        return [
            new OAuthLinkCallbackHandler($tokenStorage, $store, $registry, $linker, $urlGenerator, $translator),
            $fake,
            $request,
            $user,
            $store,
            $tokenStorage,
        ];
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

    private function request(): Request
    {
        $request = new Request(['code' => 'fake-code', 'state' => 'fake-oauth-state']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}

final class ThrowingOAuth2Client implements OAuth2ClientInterface
{
    public int $redirectCalls = 0;
    public int $registryAccesses = 0;
    public int $tokenRequests = 0;
    public int $userInfoRequests = 0;

    public function __construct(private readonly \Throwable $failure)
    {
    }

    public function setAsStateless(): void
    {
    }

    public function redirect(array $scopes = [], array $options = []): RedirectResponse
    {
        ++$this->redirectCalls;

        throw new \LogicException('Redirect is not used by this callback test.');
    }

    public function getAccessToken(array $options = []): AccessToken
    {
        ++$this->tokenRequests;

        throw $this->failure;
    }

    public function fetchUserFromToken(AccessToken $accessToken): ResourceOwnerInterface
    {
        ++$this->userInfoRequests;

        throw new \LogicException('User info must not be requested after token exchange fails.');
    }

    public function fetchUser(): ResourceOwnerInterface
    {
        return $this->fetchUserFromToken($this->getAccessToken());
    }

    public function getOAuth2Provider(): AbstractProvider
    {
        throw new \LogicException('The throwing fake has no provider.');
    }
}
