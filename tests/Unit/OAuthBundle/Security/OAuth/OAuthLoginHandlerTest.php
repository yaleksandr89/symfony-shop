<?php

declare(strict_types=1);

namespace App\Tests\Unit\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\Account\Repository\UserRepository;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthLoginHandler;
use App\OAuthBundle\Security\OAuth\OAuthNewUserRegistrar;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use App\OAuthBundle\Security\OAuth\OAuthUserResolver;
use App\Account\Security\UserChecker\DeletedUserChecker;
use App\Account\Mailer\UserRegisteredEmailSender;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Group(name: 'unit')]
final class OAuthLoginHandlerTest extends TestCase
{
    #[TestDox('Связанный пользователь возвращается без регистрации')]
    public function testLinkedUserIsReturnedWithoutRegistration(): void
    {
        $user = new User();
        $user->setGoogleId('linked-id');
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['googleId' => 'linked-id'])->willReturn($user);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::once())->method('checkPreAuth')->with($user);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $handler = $this->handler($repository, $checker, $entityManager);
        $factoryCalls = 0;

        $resolved = $handler->handle(OAuthProvider::Google, 'linked-id', 'ignored@example.test', static function () use (&$factoryCalls): User {
            ++$factoryCalls;

            return new User();
        });

        self::assertSame($user, $resolved);
        self::assertSame(0, $factoryCalls);
    }

    #[TestDox('Новый пользователь регистрируется однократно и возвращается')]
    public function testNewUserIsRegisteredOnceAndReturned(): void
    {
        $user = new class() extends User {
            public function assignId(int $id): void
            {
                $this->id = $id;
            }
        };
        $repository = $this->createMock(UserRepository::class);
        $repository->expects(self::exactly(2))->method('findOneBy')->willReturn(null);
        $checker = $this->createMock(DeletedUserChecker::class);
        $checker->expects(self::never())->method('checkPreAuth');
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hashPassword')->with($user, self::isString())->willReturn('hashed-password');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(static function () use ($user): void {
            $user->assignId(73);
        });
        $signature = new VerifyEmailSignatureComponents(new \DateTimeImmutable('@3600'), 'https://example.test/verify?id=73', 0);
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::once())->method('generateSignature')->willReturn($signature);
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::once())->method('sendEmailToClient')->with($user, $signature);
        $handler = $this->handler($repository, $checker, $entityManager, $passwordHasher, $verifyEmailHelper, $emailSender);

        $resolved = $handler->handle(
            OAuthProvider::Yandex,
            'yandex-id',
            ' new@example.test ',
            static fn (): User => $user->setIsVerified(true)
        );

        self::assertSame($user, $resolved);
        self::assertSame('new@example.test', $user->getEmail());
        self::assertSame('yandex-id', $user->getYandexId());
        self::assertFalse($user->isVerified());
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
        $passwordHasher ??= $this->createStub(UserPasswordHasherInterface::class);
        $verifyEmailHelper ??= $this->createStub(VerifyEmailHelperInterface::class);
        $emailSender ??= $this->createStub(UserRegisteredEmailSender::class);

        return new OAuthLoginHandler(
            new OAuthUserResolver($repository, $checker, $identityAccessor),
            new OAuthNewUserRegistrar($identityAccessor, $passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
        );
    }
}
