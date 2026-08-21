<?php

declare(strict_types=1);

namespace App\Account\MessageHandler\Command;

use App\Account\Mailer\ResetUserPasswordEmailSender;
use App\Account\Manager\UserManager;
use App\Account\Message\Command\ResetUserPasswordCommand;
use App\Entity\User;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsMessageHandler(fromTransport: 'async')]
class ResetUserPasswordHandler
{
    public function __construct(
        private UserManager $userManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private ResetUserPasswordEmailSender $userPasswordEmailSender,
    ) {
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
