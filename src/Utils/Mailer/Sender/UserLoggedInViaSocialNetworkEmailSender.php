<?php

declare(strict_types=1);

namespace App\Utils\Mailer\Sender;

use App\Entity\User;
use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\MailerSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserLoggedInViaSocialNetworkEmailSender
{
    // >>> Autowiring
    /**
     * @var MailerSender
     */
    private $mailerSender;

    /**
     * @required
     * @param MailerSender $mailerSender
     * @return self
     */
    public function setMailerSender(MailerSender $mailerSender): self
    {
        $this->mailerSender = $mailerSender;
        return $this;
    }

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @required
     * @param UrlGeneratorInterface $urlGenerator
     * @return self
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): self
    {
        $this->urlGenerator = $urlGenerator;
        return $this;
    }
    // Autowiring <<<

    /**
     * @param User $user
     * @param string $plainPassword
     * @return void
     */
    public function sendEmailToClient(User $user, string $plainPassword): void
    {
        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setSubject('Symfony shop - You new password!')
            ->setHtmlTemplate('front/email/client/user_logged_in_via_social_network.html.twig')
            ->setContext([
                'user' => $user,
                'plainPassword' => $plainPassword,
                'profileUrl' => $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }

    /**
     * @return MailerOptionModel
     */
    private function getMailerOptions(): MailerOptionModel
    {
        return (new MailerOptionModel());
    }
}