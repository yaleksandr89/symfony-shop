<?php

declare(strict_types=1);

namespace App\Utils\Mailer;

use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\Exception\EmailAssetUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
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

    public function __construct(
        ParameterBagInterface $parameterBag,
        private EmailAssetResolver $emailAssetResolver,
    ) {
        $this->parameterBag = $parameterBag;
    }

    public function sendTemplatedEmail(MailerOptionModel $mailerOptionModel): TemplatedEmail
    {
        $stylesheet = '';
        try {
            $stylesheet = $this->emailAssetResolver->getStylesheet();
        } catch (EmailAssetUnavailableException $exception) {
            $this->logUnavailableAsset('stylesheet', $exception);
        }

        $logoPart = null;
        $logoCid = null;
        try {
            $logoPart = (new DataPart(new File($this->emailAssetResolver->getLogoPath())))
                ->asInline()
                ->setContentId('symfony-shop-logo@symfony-shop');
            $logoCid = 'cid:'.$logoPart->getContentId();
        } catch (EmailAssetUnavailableException $exception) {
            $this->logUnavailableAsset('logo', $exception);
        }

        $email = $this->getTemplatedEmail()
            ->to($mailerOptionModel->getRecipient())
            ->subject($mailerOptionModel->getSubject())
            ->htmlTemplate($mailerOptionModel->getHtmlTemplate())
            ->context(array_merge($mailerOptionModel->getContext(), [
                'email_inline_css' => $stylesheet,
                'email_logo_cid' => $logoCid,
            ]));

        if (null !== $logoPart) {
            $email->addPart($logoPart);
        }

        if ($mailerOptionModel->getCc()) {
            $email->cc($mailerOptionModel->getCc());
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $this->logDeliveryFailure(
                'Primary email delivery failed.',
                $exception,
                'primary',
            );

            $systemMailerOptions = $this->getMailerOptions()
                ->setText(sprintf(
                    "Primary email delivery failed.\nException class: %s",
                    $exception::class,
                ));

            $this->sendSystemEmail($systemMailerOptions);

            throw $exception;
        }

        return $email;
    }

    private function sendSystemEmail(MailerOptionModel $mailerOptionModel): void
    {
        try {
            $mailerOptionModel
                ->setSubject('[Exception] An error occurred while sending the letter')
                ->setRecipient($this->parameterBag->get('admin_email'));

            $email = $this->getEmail()
                ->to($mailerOptionModel->getRecipient())
                ->subject($mailerOptionModel->getSubject())
                ->text($mailerOptionModel->getText());

            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            $this->logDeliveryFailure(
                'Fallback email delivery failed.',
                $exception,
                'fallback',
            );
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

    private function logDeliveryFailure(string $message, \Throwable $exception, string $mailStage): void
    {
        try {
            $this->logger->critical($message, [
                'exception_class' => $exception::class,
                'mail_stage' => $mailStage,
            ]);
        } catch (\Throwable) {
            // Logging is best-effort and must not replace the primary mail failure.
        }
    }

    private function logUnavailableAsset(string $asset, EmailAssetUnavailableException $exception): void
    {
        try {
            $this->logger->warning('Email decorative asset is unavailable.', [
                'asset' => $asset,
                'exception_class' => $exception::class,
            ]);
        } catch (\Throwable) {
            // Logging is best-effort and must not prevent business email delivery.
        }
    }
}
