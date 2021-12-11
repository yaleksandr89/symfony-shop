<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthGoogleController extends AbstractController
{
    /**
     * @Route("/connect/google", name="connect_google_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google_main')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/google/check", name="connect_google_check")
     *
     * @return void
     */
    public function connectCheckAction(): void
    {
    }
}
