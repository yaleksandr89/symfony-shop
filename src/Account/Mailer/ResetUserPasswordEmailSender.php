<?php

declare(strict_types=1);

namespace App\Account\Mailer;

use App\Entity\User;
use App\Mailer\Sender\BaseSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class ResetUserPasswordEmailSender extends BaseSender
{
    public function sendEmailToClient(User $user, ResetPasswordToken $resetPasswordToken): void
    {
        $emailContext = [];

        $emailContext['resetToken'] = $resetPasswordToken;
        $emailContext['user'] = $user;
        $emailContext['profileUrl'] = $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setSubject('Symfony shop - You password reset request!!')
            ->setHtmlTemplate('account/email/reset_password.html.twig')
            ->setContext($emailContext);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }
}
