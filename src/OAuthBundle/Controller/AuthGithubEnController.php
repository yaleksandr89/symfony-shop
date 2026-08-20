<?php

declare(strict_types=1);

namespace App\OAuthBundle\Controller;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\OAuthLinkCallbackHandler;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class AuthGithubEnController extends AbstractController
{
    #[Route('/connect/github-en', name: 'connect_github_en_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $clientRegistry
            ->getClient('github_en')
            ->redirect([], []);
    }

    #[Route('/connect/github-en/check', name: 'connect_github_en_check')]
    public function connectCheckAction(Request $request, OAuthLinkCallbackHandler $handler): Response
    {
        return $handler->handle($request, OAuthProvider::GithubEn);
    }
}
