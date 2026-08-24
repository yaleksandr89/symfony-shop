<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\OAuthBundle\Security\Authenticator\YandexAuthenticator;
use App\OAuthBundle\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthLoginHandler;
use App\OAuthBundle\Security\OAuth\OAuthNewUserRegistrar;
use App\OAuthBundle\Security\OAuth\OAuthUserResolver;
use App\Account\Security\UserChecker\DeletedUserChecker;
use App\Account\Mailer\UserRegisteredEmailSender;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

#[Group(name: 'unit')]
final class YandexAuthenticatorTest extends TestCase
{
    #[DataProvider('invalidEmails')]
    #[TestDox('Отсутствующий основной email даёт общий нейтральный отказ без сохранения')]
    public function testMissingDefaultEmailUsesCommonGenericDenialWithoutPersistence(?string $email): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['yandexId' => 'yandex-id'])->willReturn(null);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $badge = $this->authenticate($this->yandexUser($email), $this->handler($repository, $checker, $entityManager));

        $this->expectException(OAuthLoginDeniedException::class);
        $this->expectExceptionMessage('OAuth authentication could not be completed.');
        $badge->getUser();
    }

    #[TestDox('Данные провайдера нормализуются и регистрируются общим обработчиком')]
    public function testProviderDataIsNormalizedAndRegisteredThroughCommonHandler(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?User => match ($criteria) {
            ['yandexId' => 'yandex-id'] => null,
            ['email' => 'user@example.test'] => null,
        });
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $persistedUser = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(User::class))->willReturnCallback(
            static function (User $user) use (&$persistedUser): void {
                $persistedUser = $user;
            }
        );
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(
            static function () use (&$persistedUser): void {
                self::assertInstanceOf(User::class, $persistedUser);
                (new \ReflectionProperty(User::class, 'id'))->setValue($persistedUser, 51);
            }
        );
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hashPassword')->willReturn('hashed-password');
        $signature = new VerifyEmailSignatureComponents(new \DateTimeImmutable('@3600'), 'https://example.test/verify?id=51', 0);
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::once())->method('generateSignature')->with(
            'main_verify_email',
            '51',
            'user@example.test',
            ['id' => '51']
        )->willReturn($signature);
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::once())->method('sendEmailToClient')->with(self::isInstanceOf(User::class), $signature);

        $badge = $this->authenticate(
            $this->yandexUser('  user@example.test  '),
            $this->handler($repository, $checker, $entityManager, $passwordHasher, $verifyEmailHelper, $emailSender)
        );
        $user = $badge->getUser();

        self::assertSame('user@example.test', $user->getEmail());
        self::assertSame('yandex-id', $user->getYandexId());
        self::assertFalse($user->isVerified());
        self::assertSame('hashed-password', $user->getPassword());
    }

    #[TestDox('Сбой перенаправляет на локализованную страницу входа с нейтральным flash-сообщением')]
    public function testFailureRedirectsToLocalizedLoginWithNeutralDangerFlash(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::once())->method('generate')->with('main_login', ['_locale' => 'en'])->willReturn('/en/login');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')->with('oauth.authentication.failure')->willReturn('Translated neutral failure');
        $authenticator = new YandexAuthenticator(
            new ClientRegistry($this->createStub(ContainerInterface::class), []),
            $this->handler(
                $this->createStub(UserRepository::class),
                $this->createStub(DeletedUserChecker::class),
                $this->createStub(EntityManagerInterface::class)
            ),
            $router,
            $translator
        );
        $request = new Request();
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $authenticator->onAuthenticationFailure($request, new OAuthLoginDeniedException());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/en/login', $response->getTargetUrl());
        self::assertSame(['Translated neutral failure'], $request->getSession()->getFlashBag()->get('danger'));
    }

    /** @return iterable<string, array{?string}> */
    public static function invalidEmails(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    private function authenticate(YandexResourceOwner $yandexUser, OAuthLoginHandler $handler): UserBadge
    {
        $client = $this->createMock(OAuth2ClientInterface::class);
        $accessToken = new AccessToken(['access_token' => 'test-access-token']);
        $client->expects(self::once())->method('getAccessToken')->willReturn($accessToken);
        $client->expects(self::once())->method('fetchUserFromToken')->with($accessToken)->willReturn($yandexUser);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('yandex-client')->willReturn($client);

        $authenticator = new YandexAuthenticator(
            new ClientRegistry($container, ['yandex_main' => 'yandex-client']),
            $handler,
            $this->createStub(RouterInterface::class),
            $this->createStub(TranslatorInterface::class)
        );
        $passport = $authenticator->authenticate(new Request());
        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);

        return $badge;
    }

    private function handler(
        UserRepository $repository,
        DeletedUserChecker $checker,
        EntityManagerInterface $entityManager,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?VerifyEmailHelperInterface $verifyEmailHelper = null,
        ?UserRegisteredEmailSender $emailSender = null,
    ): OAuthLoginHandler {
        $identityAccessor = new OAuthIdentityAccessor();

        return new OAuthLoginHandler(
            new OAuthUserResolver($repository, $checker, $identityAccessor),
            new OAuthNewUserRegistrar(
                $identityAccessor,
                $passwordHasher ?? $this->createStub(UserPasswordHasherInterface::class),
                $entityManager,
                $verifyEmailHelper ?? $this->createStub(VerifyEmailHelperInterface::class),
                $emailSender ?? $this->createStub(UserRegisteredEmailSender::class)
            )
        );
    }

    private function yandexUser(?string $email): YandexResourceOwner
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
