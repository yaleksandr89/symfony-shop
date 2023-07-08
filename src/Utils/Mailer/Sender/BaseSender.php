<?php

namespace App\Utils\Mailer\Sender;

use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\MailerSender;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
     */
    public function setMailerSender(MailerSender $mailerSender): BaseSender
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
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): BaseSender
    {
        $this->urlGenerator = $urlGenerator;

        return $this;
    }

    // Autowiring <<<

    protected $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    protected function getMailerOptions(): MailerOptionModel
    {
        return new MailerOptionModel();
    }
}
