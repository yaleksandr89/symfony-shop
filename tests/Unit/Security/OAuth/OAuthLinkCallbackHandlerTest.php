<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OAuth\OAuthAccountLinker;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthLinkCallbackHandler;
use App\Security\OAuth\OAuthLinkIntentStore;
use App\Security\OAuth\OAuthProvider;
use App\Tests\TestUtils\OAuth\FakeOAuth2Client;
use App\Tests\TestUtils\OAuth\FakeOAuthResourceOwner;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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

    /** @return array{OAuthLinkCallbackHandler, FakeOAuth2Client, Request, User, OAuthLinkIntentStore, TokenStorage} */
    private function handler(bool $loggedIn, bool $pending): array
    {
        $request = $this->request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $store = new OAuthLinkIntentStore($requestStack, new MockClock('2026-08-06 12:00:00 UTC'));
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, 13);
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

        $fake = new FakeOAuth2Client($requestStack, new FakeOAuthResourceOwner('external-id'));
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
        $registry = new ClientRegistry($container, ['google_main' => 'fake.oauth']);
        $request->getSession()->set(OAuth2Client::OAUTH2_SESSION_STATE_KEY, 'fake-oauth-state');
        $shouldLink = $loggedIn && $pending;
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($shouldLink ? self::once() : self::never())->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($shouldLink ? self::once() : self::never())->method('flush');
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

    private function request(): Request
    {
        $request = new Request(['code' => 'fake-code', 'state' => 'fake-oauth-state']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
