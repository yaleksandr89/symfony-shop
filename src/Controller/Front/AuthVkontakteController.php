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
     * @Route("/connect/vk", name="connect_vk_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('vk_main')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/vk/check", name="connect_vk_check")
     *
     * @return void
     */
    public function connectCheckAction(): void
    {
    }
}
