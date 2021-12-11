<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthFacebookController extends AbstractController
{
    /**
     * @Route("/connect/facebook", name="connect_facebook_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('facebook_main')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/facebook/check", name="connect_facebook_check")
     *
     * @return void
     */
    public function connectCheckAction(): void
    {
    }
}
