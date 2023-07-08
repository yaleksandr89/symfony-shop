<?php

namespace App\Utils\Mailer\Sender;

use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\MailerSender;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\Attribute\Required;

abstract class BaseSender
{
    protected MailerSender $mailerSender;

    #[Required]
    public function setMailerSender(MailerSender $mailerSender): BaseSender
    {
        $this->mailerSender = $mailerSender;

        return $this;
    }

    protected UrlGeneratorInterface $urlGenerator;

    #[Required]
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): BaseSender
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    protected ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    protected function getMailerOptions(): MailerOptionModel
    {
        return new MailerOptionModel();
    }
}
