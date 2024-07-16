<?php

declare(strict_types=1);

namespace App\Controller\Front;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthGithubRusController extends AbstractController
{
    #[Route('/connect/github-ru', name: 'connect_github_ru_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_ru')
            ->redirect([], []);
    }

    #[Route('/connect/github-ru/check', name: 'connect_github_ru_check')]
    public function connectCheckAction(): void
    {
    }
}
