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

class MailerSender
{
    // >>> Autowiring
    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @required
     *
     * @param MailerInterface $mailer
     *
     * @return MailerSender
     */
    public function setMailer(MailerInterface $mailer): MailerSender
    {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @required
     *
     * @param LoggerInterface $logger
     *
     * @return MailerSender
     */
    public function setLogger(LoggerInterface $logger): MailerSender
    {
        $this->logger = $logger;

        return $this;
    }

    // Autowiring <<<

    protected $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * @param MailerOptionModel $mailerOptionModel
     *
     * @return TemplatedEmail
     */
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

    /**
     * @param MailerOptionModel $mailerOptionModel
     *
     * @return Email
     */
    private function sendSystemEmail(MailerOptionModel $mailerOptionModel): Email
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

        return $email;
    }

    /**
     * @return TemplatedEmail
     */
    private function getTemplatedEmail(): TemplatedEmail
    {
        return new TemplatedEmail();
    }

    /**
     * @return MailerOptionModel
     */
    private function getMailerOptions(): MailerOptionModel
    {
        return new MailerOptionModel();
    }

    /**
     * @return Email
     */
    private function getEmail(): Email
    {
        return new Email();
    }
}
