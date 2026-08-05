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
use Symfony\Component\Routing\Annotation\Route;

class AuthVkontakteController extends AbstractController
{
    #[Route('/connect/vkontakte', name: 'connect_vkontakte_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $clientRegistry
            ->getClient('vkontakte_main')
            ->redirect([], []);
    }

    #[Route('/connect/vkontakte/check', name: 'connect_vkontakte_check')]
    public function connectCheckAction(Request $request, OAuthLinkCallbackHandler $handler): Response
    {
        return $handler->handle($request, OAuthProvider::Vkontakte);
    }
}
