<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Command;

use App\Entity\User;
use App\Messenger\Message\Command\ResetUserPasswordCommand;
use App\Utils\Mailer\Sender\ResetUserPasswordEmailSender;
use App\Utils\Manager\UserManager;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
class ResetUserPasswordHandler
{
    private UserManager $userManager;

    private ResetPasswordHelperInterface $resetPasswordHelper;

    private ResetUserPasswordEmailSender $userPasswordEmailSender;

    public function __construct(
        UserManager $userManager,
        ResetPasswordHelperInterface $resetPasswordHelper,
        ResetUserPasswordEmailSender $userPasswordEmailSender
    ) {
        $this->userManager = $userManager;
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->userPasswordEmailSender = $userPasswordEmailSender;
    }

    public function __invoke(ResetUserPasswordCommand $resetUserPasswordCommand): void
    {
        $email = $resetUserPasswordCommand->getEmail();

        /** @var User|null $user */
        $user = $this->userManager->getRepository()->findOneBy(['email' => $email]);

        if (!$user) {
            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            $this->userPasswordEmailSender->sendEmailToClient($user, $resetToken);
        } catch (ResetPasswordExceptionInterface $e) {
            // ...
        }
    }
}
