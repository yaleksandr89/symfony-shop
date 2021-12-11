<?php

declare(strict_types=1);

namespace App\Messenger\MessageHandler\Command;

use App\Entity\User;
use App\Messenger\Message\Command\ResetUserPasswordCommand;
use App\Utils\Mailer\Sender\ResetUserPasswordEmailSender;
use App\Utils\Manager\UserManager;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetUserPasswordHandler implements MessageHandlerInterface
{
    /** @var UserManager */
    private $userManager;

    /** @var ResetPasswordHelperInterface */
    private $resetPasswordHelper;

    /** @var ResetUserPasswordEmailSender */
    private $userPasswordEmailSender;

    /**
     * @param UserManager                  $userManager
     * @param ResetPasswordHelperInterface $resetPasswordHelper
     * @param ResetUserPasswordEmailSender $userPasswordEmailSender
     */
    public function __construct(
        UserManager $userManager,
        ResetPasswordHelperInterface $resetPasswordHelper,
        ResetUserPasswordEmailSender $userPasswordEmailSender
    ) {
        $this->userManager = $userManager;
        $this->resetPasswordHelper = $resetPasswordHelper;
        $this->userPasswordEmailSender = $userPasswordEmailSender;
    }

    public function __invoke(ResetUserPasswordCommand $resetUserPasswordCommand)
    {
        $email = $resetUserPasswordCommand->getEmail();
        $resetToken = null;

        /** @var User $user */
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
