<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Security\Authenticator\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $session = $request->getSession();
        $returnIntent = $request->query->all()['return'] ?? null;

        if (LoginFormAuthenticator::CART_RETURN_INTENT === $returnIntent && null === $this->getUser()) {
            $session->set(LoginFormAuthenticator::RETURN_INTENT_SESSION_KEY, $returnIntent);
        } else {
            $session->remove(LoginFormAuthenticator::RETURN_INTENT_SESSION_KEY);
        }

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
