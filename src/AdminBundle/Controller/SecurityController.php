<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\AdminBundle\Security\Authenticator\LoginFormAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends BaseAdminController
{
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('@Admin/security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    public function logout(): RedirectResponse
    {
        return $this->redirectToRoute(LoginFormAuthenticator::LOGIN_ROUTE);
    }
}
