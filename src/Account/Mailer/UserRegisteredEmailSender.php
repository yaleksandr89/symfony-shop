<?php

declare(strict_types=1);

namespace App\Account\Mailer;

use App\Entity\User;
use App\Mailer\Sender\BaseSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;

class UserRegisteredEmailSender extends BaseSender
{
    public function sendEmailToClient(User $user, VerifyEmailSignatureComponents $signatureComponents): void
    {
        $emailContext = [];

        $emailContext['signedUrl'] = $signatureComponents->getSignedUrl();
        $emailContext['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $emailContext['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();
        $emailContext['user'] = $user;
        $emailContext['profileUrl'] = $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setSubject('Symfony shop - Please confirm your email!')
            ->setHtmlTemplate('account/email/confirmation_email.html.twig')
            ->setContext($emailContext);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }
}
