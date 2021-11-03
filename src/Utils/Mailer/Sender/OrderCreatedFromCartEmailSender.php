<?php

declare(strict_types=1);

namespace App\Utils\Mailer\Sender;

use App\Entity\Order;
use App\Entity\User;
use App\Utils\Mailer\DTO\MailerOptionModel;
use App\Utils\Mailer\MailerSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OrderCreatedFromCartEmailSender
{
    // >>> Autowiring
    /**
     * @var MailerSender
     */
    private $mailerSender;

    /**
     * @required
     * @param MailerSender $mailerSender
     * @return OrderCreatedFromCartEmailSender
     */
    public function setMailerSender(MailerSender $mailerSender): OrderCreatedFromCartEmailSender
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
     * @return OrderCreatedFromCartEmailSender
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): OrderCreatedFromCartEmailSender
    {
        $this->urlGenerator = $urlGenerator;
        return $this;
    }
    // Autowiring <<<

    /**
     * @param Order $order
     * @return void
     */
    public function sendEmailToClient(Order $order): void
    {
        /** @var User $user */
        $user = $order->getOwner();

        $mailerOptions = $this->getMailerOptions()
            ->setRecipient($user->getEmail())
            ->setCc('y.aleksandr89@yandex.ru')
            ->setSubject('Symfony shop - Thank you for  your purchase!')
            ->setHtmlTemplate('front/email/client/created_order_from_cart.html.twig')
            ->setContext([
                'order' => $order,
                'profileUrl' => $this->urlGenerator->generate('main_profile_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);

        $this->mailerSender->sendTemplatedEmail($mailerOptions);
    }

    /**
     * @param Order $order
     * @return void
     */
    public function sendEmailToManager(Order $order): void
    {
        /** @var User $user */
        $user = $order->getOwner();

        $mailerOptions = $this->getMailerOptions()
            ->setRecipient('y.aleksandr89@yandex.ru')
            ->setSubject("Client created order (ID: {$order->getId()})")
            ->setHtmlTemplate('front/email/manager/created_order_from_cart.html.twig')
            ->setContext([
                'order' => $order,
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