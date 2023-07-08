<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthYandexController extends AbstractController
{
    /**
     * @Route("/connect/yandex", name="connect_yandex_start")
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('yandex_main')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/yandex/check", name="connect_yandex_check")
     */
    public function connectCheckAction(): void
    {
    }
}
