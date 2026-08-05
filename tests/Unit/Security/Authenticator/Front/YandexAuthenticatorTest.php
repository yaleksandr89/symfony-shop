<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Authenticator\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Authenticator\Front\YandexAuthenticator;
use App\Security\OAuth\OAuthUserResolver;
use App\Security\UserChecker\DeletedUserChecker;
use App\Utils\Manager\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

#[Group(name: 'unit')]
final class YandexAuthenticatorTest extends TestCase
{
    #[DataProvider('invalidEmails')]
    public function testInvalidDefaultEmailIsRejectedBeforeResolverAndPersistence(?string $email): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::never())->method('findOneBy');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');

        $badge = $this->authenticate($this->yandexUser($email), new OAuthUserResolver($repository, $checker), new UserManager($entityManager), $eventDispatcher);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Unable to authenticate with this provider.');
        $badge->getUser();
    }

    public function testTrimmedDefaultEmailIsPassedToNewUserFactory(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?User => match ($criteria) {
            ['yandexId' => 'yandex-id'] => null,
            ['email' => 'user@example.test'] => null,
        });
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class));
        $entityManager->expects(self::once())->method('flush');
        $userManager = new UserManager($entityManager);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hashPassword')->willReturn('hashed-password');
        $userManager->setUserPasswordHasher($passwordHasher);
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::once())->method('generateSignature')->with(
            'main_verify_email',
            '',
            'user@example.test',
            ['id' => ''],
        )->willReturn(new VerifyEmailSignatureComponents(new \DateTimeImmutable('@3600'), 'https://example.test/verify', 0));
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(\App\Event\UserLoggedInViaSocialNetworkEvent::class));

        $badge = $this->authenticate($this->yandexUser('  user@example.test  '), new OAuthUserResolver($repository, $checker), $userManager, $eventDispatcher, $verifyEmailHelper, true);
        $user = $badge->getUser();

        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('yandex-id', $user->getYandexId());
    }

    public static function invalidEmails(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    private function authenticate(YandexResourceOwner $yandexUser, OAuthUserResolver $resolver, UserManager $userManager, EventDispatcherInterface $eventDispatcher, ?VerifyEmailHelperInterface $verifyEmailHelper = null, bool $expectsNewUserFlow = false): UserBadge
    {
        $client = $this->createMock(OAuth2ClientInterface::class);
        $accessToken = new AccessToken(['access_token' => 'test-access-token']);
        $client->expects(self::once())->method('getAccessToken')->willReturn($accessToken);
        $client->expects(self::once())->method('fetchUserFromToken')->with($accessToken)->willReturn($yandexUser);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('yandex-client')->willReturn($client);
        $security = $this->createMock(Security::class);
        $security->expects($expectsNewUserFlow ? self::once() : self::never())->method('getUser')->willReturn(null);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($expectsNewUserFlow ? self::exactly(2) : self::never())->method('trans')->willReturn('translated');
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('generate');

        if (null === $verifyEmailHelper) {
            $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
            $verifyEmailHelper->expects(self::never())->method('generateSignature');
        }

        $authenticator = new YandexAuthenticator(
            new ClientRegistry($container, ['yandex_main' => 'yandex-client']),
            $userManager,
            $resolver,
            $router,
            $eventDispatcher,
            $verifyEmailHelper,
            $translator,
        );
        $authenticator->setSecurity($security);
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $passport = $authenticator->authenticate($request);

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);

        return $badge;
    }

    private function yandexUser(?string $email = null): YandexResourceOwner
    {
        return new YandexResourceOwner(array_filter([
            'id' => 'yandex-id',
            'login' => 'yandex-login',
            'client_id' => 'client-id',
            'psuid' => 'psuid',
            'default_email' => $email,
        ], static fn (?string $value): bool => null !== $value));
    }
}
