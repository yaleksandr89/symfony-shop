<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messenger\MessageHandler\Command;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Messenger\Message\Command\ResetUserPasswordCommand;
use App\Messenger\MessageHandler\Command\ResetUserPasswordHandler;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Group(name: 'integration')]
final class ResetUserPasswordHandlerTest extends KernelTestCase
{
    #[TestDox('Известный пользователь получает письмо с рабочим reset token')]
    public function testKnownUserGetsUsableResetLink(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $email = 'reset-handler-'.str_replace('.', '', uniqid('', true)).'@example.test';
        $user = (new User())
            ->setEmail($email)
            ->setPassword('existing-password-hash')
            ->setIsVerified(true);
        $entityManager->persist($user);
        $entityManager->flush();

        self::getContainer()->get(ResetUserPasswordHandler::class)(new ResetUserPasswordCommand($email));

        $requestRepository = self::getContainer()->get(ResetPasswordRequestRepository::class);
        $request = $requestRepository->findOneBy(['user' => $user]);
        self::assertInstanceOf(ResetPasswordRequest::class, $request);
        self::assertEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertInstanceOf(Email::class, $message);
        self::assertEmailAddressContains($message, 'to', $email);
        self::assertEmailHtmlBodyContains($message, '/reset-password/reset/');
        $html = $message->getHtmlBody();
        self::assertNotNull($html);
        self::assertSame(1, preg_match('#/reset-password/reset/([A-Za-z0-9]{40})#', $html, $matches));

        $entityManager->clear();
        $tokenUser = self::getContainer()->get(ResetPasswordHelperInterface::class)
            ->validateTokenAndFetchUser($matches[1]);
        self::assertInstanceOf(User::class, $tokenUser);
        self::assertSame($user->getId(), $tokenUser->getId());
    }

    #[TestDox('Неизвестный email остаётся нейтральным no-op без token и письма')]
    public function testUnknownUserCreatesNoResetRequestOrEmail(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(ResetPasswordRequestRepository::class);
        $beforeCount = $repository->count([]);

        self::getContainer()->get(ResetUserPasswordHandler::class)(
            new ResetUserPasswordCommand('unknown-handler-user@example.test'),
        );

        self::assertSame($beforeCount, $repository->count([]));
        self::assertEmailCount(0);
    }
}
