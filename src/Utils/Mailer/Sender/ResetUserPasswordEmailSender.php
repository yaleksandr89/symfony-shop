<?php

declare(strict_types=1);

namespace App\Utils\Mailer\Sender;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

class ResetUserPasswordEmailSender extends BaseSender
{
    /**
     * @param User               $user
     * @param ResetPasswordToken $resetPasswordToken
     */
    public function sendEmailToClient(User $user, ResetPasswordToken $resetPasswordToken): void
    {
        $emailContext = [];

        $emailContext['resetToken'] = $resetPasswordToken;
        $emailContext['user'] = $user;
        $emailContext['profileUrl'] = $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setSubject('Symfony shop - You password reset request!!')
            ->setHtmlTemplate('front/email/security/reset_password.html.twig')
            ->setContext($emailContext);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }
}
