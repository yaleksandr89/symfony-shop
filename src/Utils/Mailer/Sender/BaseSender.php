<?php

namespace App\Utils\Mailer\Sender;

use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\MailerSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class BaseSender
{
    // >>> Autowiring
    /**
     * @var MailerSender
     */
    protected $mailerSender;

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
    protected $urlGenerator;

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
     * @return MailerOptionModel
     */
    protected function getMailerOptions(): MailerOptionModel
    {
        return (new MailerOptionModel());
    }
}