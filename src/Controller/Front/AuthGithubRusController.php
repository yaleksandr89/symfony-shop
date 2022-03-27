<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthGithubRusController extends AbstractController
{
    /**
     * @Route("/connect/github-rus", name="connect_github_rus_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_rus')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/github-rus/check", name="connect_github_rus_check")
     *
     * @return void
     */
    public function connectCheckAction(): void
    {
    }
}
