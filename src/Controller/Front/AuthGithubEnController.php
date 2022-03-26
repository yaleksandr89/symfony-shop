<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthGithubEnController extends AbstractController
{
    /**
     * @Route("/connect/github-en", name="connect_github_en_start")
     *
     * @param ClientRegistry $clientRegistry
     *
     * @return RedirectResponse
     */
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_en')
            ->redirect([], []);
    }

    /**
     * @Route("/connect/github-en/check", name="connect_github_en_check")
     *
     * @return void
     */
    public function connectCheckAction()
    {
    }
}
