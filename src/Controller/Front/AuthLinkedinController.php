<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Security\OAuth\OAuthLinkCallbackHandler;
use App\Security\OAuth\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AuthLinkedinController extends AbstractController
{
    #[Route('/connect/linkedin', name: 'connect_linkedin_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $clientRegistry->getClient('linkedin_main')->redirect([], []);
    }

    #[Route('/connect/linkedin/check', name: 'connect_linkedin_check')]
    public function connectCheckAction(Request $request, OAuthLinkCallbackHandler $handler): Response
    {
        return $handler->handle($request, OAuthProvider::Linkedin);
    }
}
