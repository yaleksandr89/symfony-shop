<?php

declare(strict_types=1);

namespace App\Utils\Mailer\Sender;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserLoggedInViaSocialNetworkEmailSender extends BaseSender
{
    /**
     * @param User $user
     * @param string $plainPassword
     * @param array $verifyEmail
     * @return void
     */
    public function sendEmailToClient(User $user, string $plainPassword, array $verifyEmail): void
    {
        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setSubject('Symfony shop - You new password!')
            ->setHtmlTemplate('front/email/client/user_logged_in_via_social_network.html.twig')
            ->setContext([
                'user' => $user,
                'plainPassword' => $plainPassword,
                'profileUrl' => $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'signedUrl' => $verifyEmail['signedUrl'],
                'expiresAtMessageKey' => $verifyEmail['expiresAtMessageKey'],
                'expiresAtMessageData' => $verifyEmail['expiresAtMessageData'],
            ]);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }
}