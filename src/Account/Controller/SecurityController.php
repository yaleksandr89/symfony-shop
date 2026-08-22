<?php

declare(strict_types=1);

namespace App\Account\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $request->getSession()->set('HTTP_REFERER', $request->server->get('HTTP_REFERER'));

        if ($this->getUser()) {
            return $this->redirectToRoute('main_profile_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('account/security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    public function logout(): RedirectResponse
    {
        return $this->redirectToRoute('main_profile_index');
    }
}
