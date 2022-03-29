<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthVkontakteController extends AbstractController
{
    /**
     * @Route("/connect/vkontakte", name="connect_vkontakte_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('vkontakte_main')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/vkontakte/check", name="connect_vkontakte_check")
     *
     * @return void
     */
    public function connectCheckAction(): void
    {
    }
}
