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

class AuthYandexController extends AbstractController
{
    #[Route('/connect/yandex', name: 'connect_yandex_start')]
    public function connectAction(ClientRegistry $clientRegistry): RedirectResponse
    {
        if ($this->getUser() instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $clientRegistry
            ->getClient('yandex_main')
            ->redirect([], []);
    }

    #[Route('/connect/yandex/check', name: 'connect_yandex_check')]
    public function connectCheckAction(Request $request, OAuthLinkCallbackHandler $handler): Response
    {
        return $handler->handle($request, OAuthProvider::Yandex);
    }
}
