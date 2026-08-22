<?php

declare(strict_types=1);

namespace App\Tests\Integration\Account\MessageHandler\Event;

use App\Account\Mailer\UserRegisteredEmailSender;
use App\Account\Message\Event\EventUserRegisteredEvent;
use App\Account\MessageHandler\Event\UserRegisteredHandler;
use App\Account\Repository\UserRepository;
use App\Account\Security\Verifier\EmailVerifier;
use App\Entity\User;
use App\Tests\TestUtils\Fixtures\UserFixtures;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

#[Group(name: 'integration')]
final class UserRegisteredHandlerTest extends KernelTestCase
{
    #[TestDox('Известный пользователь получает реальную подписанную ссылку через почтовую границу')]
    public function testKnownUserSendsRealVerificationSignatureOnce(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => UserFixtures::USER_1_EMAIL]);
        self::assertInstanceOf(User::class, $user);
        $userId = $user->getId();
        self::assertNotNull($userId);

        $sentSignature = null;
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::once())
            ->method('sendEmailToClient')
            ->with(
                self::identicalTo($user),
                self::callback(static function (VerifyEmailSignatureComponents $signature) use (&$sentSignature): bool {
                    $sentSignature = $signature;

                    return true;
                }),
            );
        $handler = new UserRegisteredHandler(
            $container->get(EmailVerifier::class),
            $userRepository,
            $emailSender,
        );

        $handler(new EventUserRegisteredEvent($userId));

        self::assertInstanceOf(VerifyEmailSignatureComponents::class, $sentSignature);
        $signedUrl = $sentSignature->getSignedUrl();
        self::assertNotSame('', $signedUrl);
        self::assertSame('/ru/verify/email', parse_url($signedUrl, PHP_URL_PATH));
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);
        self::assertSame((string) $userId, $query['id'] ?? null);
        self::assertArrayHasKey('expires', $query);
        self::assertArrayHasKey('signature', $query);
    }

    #[TestDox('Неизвестный пользователь завершается без письма и изменений хранилища')]
    public function testMissingUserDoesNotSendOrMutatePersistence(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $missingUserId = 2_147_483_647;
        self::assertNull($userRepository->find($missingUserId));
        $userCount = $userRepository->count([]);
        $emailSender = $this->createMock(UserRegisteredEmailSender::class);
        $emailSender->expects(self::never())->method('sendEmailToClient');
        $handler = new UserRegisteredHandler(
            $container->get(EmailVerifier::class),
            $userRepository,
            $emailSender,
        );

        $handler(new EventUserRegisteredEvent($missingUserId));

        $entityManager->clear();
        self::assertSame($userCount, $userRepository->count([]));
        self::assertNull($userRepository->find($missingUserId));
    }
}
