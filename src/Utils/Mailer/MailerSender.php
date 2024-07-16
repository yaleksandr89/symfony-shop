<?php

declare(strict_types=1);

namespace App\Utils\Mailer;

use App\Utils\Mailer\DTO\MailerOptionModel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required;

class MailerSender
{
    private MailerInterface $mailer;

    #[Required]
    public function setMailer(MailerInterface $mailer): MailerSender
    {
        $this->mailer = $mailer;

        return $this;
    }

    private LoggerInterface $logger;

    #[Required]
    public function setLogger(LoggerInterface $logger): MailerSender
    {
        $this->logger = $logger;

        return $this;
    }

    protected ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function sendTemplatedEmail(MailerOptionModel $mailerOptionModel): TemplatedEmail
    {
        $email = $this->getTemplatedEmail()
            ->to($mailerOptionModel->getRecipient())
            ->subject($mailerOptionModel->getSubject())
            ->htmlTemplate($mailerOptionModel->getHtmlTemplate())
            ->context($mailerOptionModel->getContext());

        if ($mailerOptionModel->getCc()) {
            $email->cc($mailerOptionModel->getCc());
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logger->critical($mailerOptionModel->getSubject(), [
                'errorText' => $exception->getTraceAsString(),
            ]);

            $systemMailerOptions = $this->getMailerOptions()
                ->setText($exception->getTraceAsString());

            $this->sendSystemEmail($systemMailerOptions);
        }

        return $email;
    }

    private function sendSystemEmail(MailerOptionModel $mailerOptionModel): void
    {
        $mailerOptionModel
            ->setSubject('[Exception] An error occurred while sending the letter')
            ->setRecipient($this->parameterBag->get('admin_email'));

        $email = $this->getEmail()
            ->to($mailerOptionModel->getRecipient())
            ->subject($mailerOptionModel->getSubject())
            ->text($mailerOptionModel->getText());

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $ex) {
            $this->logger->critical($mailerOptionModel->getSubject(), [
                'errorText' => $ex->getTraceAsString(),
            ]);
        }
    }

    private function getTemplatedEmail(): TemplatedEmail
    {
        return new TemplatedEmail();
    }

    private function getMailerOptions(): MailerOptionModel
    {
        return new MailerOptionModel();
    }

    private function getEmail(): Email
    {
        return new Email();
    }
}
