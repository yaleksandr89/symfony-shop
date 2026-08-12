<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\OAuth;

use App\Entity\User;
use App\Security\OAuth\Exception\OAuthLoginDeniedException;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthNewUserRegistrar;
use App\Security\OAuth\OAuthProvider;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Group(name: 'unit')]
final class OAuthNewUserRegistrarTest extends TestCase
{
    #[TestDox('Сохраняет пользователя, затем формирует подпись и отправляет письмо')]
    public function testPersistsBeforeOneFlushThenBuildsSignatureAndSendsStandardEmail(): void
    {
        $user = new class() extends User {
            public function assignId(int $id): void
            {
                $this->id = $id;
            }
        };
        $user->setEmail('oauth-new@example.test')->setIsVerified(true);
        $user->setGoogleId('google-external-id');
        $rawSecret = null;
        $step = 0;

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hashPassword')->with($user, self::callback(
            static function (string $secret) use (&$rawSecret): bool {
                $rawSecret = $secret;

                return 64 === strlen($secret) && ctype_xdigit($secret);
            }
        ))->willReturn('hashed-internal-password');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user)->willReturnCallback(
            static function () use (&$step): void {
                self::assertSame(0, $step);
                $step = 1;
            }
        );
        $entityManager->expects(self::once())->method('flush')->willReturnCallback(
            static function () use (&$step, $user): void {
                self::assertSame(1, $step);
                $user->assignId(42);
                $step = 2;
            }
        );

        $signature = new VerifyEmailSignatureComponents(new \DateTimeImmutable('@3600'), 'https://example.test/verify?id=42', 0);
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::once())->method('generateSignature')->with(
            'main_verify_email',
            '42',
            'oauth-new@example.test',
            ['id' => '42']
        )->willReturnCallback(static function () use (&$step, $signature): VerifyEmailSignatureComponents {
            self::assertSame(2, $step);
            $step = 3;

            return $signature;
        });

        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::once())->method('sendEmailToClient')->with($user, $signature)->willReturnCallback(
            static function () use (&$step): void {
                self::assertSame(3, $step);
                $step = 4;
            }
        );

        $this->registrar($passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
            ->register($user, OAuthProvider::Google);

        self::assertSame(4, $step);
        self::assertFalse($user->isVerified());
        self::assertSame('hashed-internal-password', $user->getPassword());
        self::assertIsString($rawSecret);
        self::assertNotSame($rawSecret, $user->getPassword());
        self::assertStringNotContainsString($rawSecret, $signature->getSignedUrl());
    }

    #[TestDox('Сбой уникальности превращается в нейтральный отказ без подписи и письма')]
    public function testUniqueConstraintFailureBecomesGenericDenialWithoutSignatureOrEmail(): void
    {
        $user = (new User())->setEmail('race@example.test');
        $user->setYandexId('secret-external-id');
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())->method('hashPassword')->willReturn('hashed-password');
        $driverException = new class('database detail race@example.test secret-external-id') extends \RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): ?string
            {
                return '23000';
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush')->willThrowException(
            new UniqueConstraintViolationException($driverException, null)
        );
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::never())->method('generateSignature');
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');

        try {
            $this->registrar($passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
                ->register($user, OAuthProvider::Yandex);
            self::fail('A unique race must deny OAuth authentication.');
        } catch (OAuthLoginDeniedException $exception) {
            self::assertSame('OAuth authentication could not be completed.', $exception->getMessageKey());
            self::assertStringNotContainsString('race@example.test', $exception->getMessage());
            self::assertStringNotContainsString('secret-external-id', $exception->getMessage());
            self::assertStringNotContainsString('database detail', $exception->getMessage());
        }
    }

    #[TestDox('Неожиданный сбой сохранения пробрасывается без подмены до подписи и письма')]
    public function testUnexpectedFlushFailureRethrowsSameInstanceBeforeSignatureOrEmail(): void
    {
        $user = (new User())->setEmail('failure@example.test');
        $user->setVkontakteId('vk-id');
        $passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed-password');
        $failure = new \RuntimeException('storage unavailable');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($user);
        $entityManager->expects(self::once())->method('flush')->willThrowException($failure);
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::never())->method('generateSignature');
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');

        try {
            $this->registrar($passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
                ->register($user, OAuthProvider::Vkontakte);
            self::fail('The storage failure must be rethrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
    }

    #[TestDox('Отсутствующий email отклоняется до хеширования, сохранения, подписи и письма')]
    #[DataProvider('missingEmails')]
    public function testMissingEmailIsDeniedBeforeAnyRegistrationSideEffect(?string $email): void
    {
        $user = new class() extends User {
            public function assignNullableEmail(?string $email): void
            {
                $this->email = $email;
            }
        };
        $user->assignNullableEmail($email);
        $user->setFacebookId('facebook-id');
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('hashPassword');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::never())->method('generateSignature');
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');

        $this->expectException(OAuthLoginDeniedException::class);
        $this->registrar($passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
            ->register($user, OAuthProvider::Facebook);
    }

    /** @return iterable<string, array{?string}> */
    public static function missingEmails(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
    }

    #[TestDox('Без авторитетного идентификатора обработка останавливается до пароля и сохранения')]
    public function testMissingAuthoritativeIdentityIsDeniedBeforePasswordOrPersistence(): void
    {
        $user = (new User())->setEmail('missing-identity@example.test');
        $user->setGithubId(null);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::never())->method('hashPassword');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $verifyEmailHelper = $this->createMock(VerifyEmailHelperInterface::class);
        $verifyEmailHelper->expects(self::never())->method('generateSignature');
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');

        $this->expectException(OAuthLoginDeniedException::class);
        $this->registrar($passwordHasher, $entityManager, $verifyEmailHelper, $emailSender)
            ->register($user, OAuthProvider::GithubEn);
    }

    private function registrar(
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        VerifyEmailHelperInterface $verifyEmailHelper,
        UserRegisteredEmailSender $emailSender,
    ): OAuthNewUserRegistrar {
        return new OAuthNewUserRegistrar(
            new OAuthIdentityAccessor(),
            $passwordHasher,
            $entityManager,
            $verifyEmailHelper,
            $emailSender
        );
    }
}
