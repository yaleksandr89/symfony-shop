<?php

declare(strict_types=1);

namespace App\Account\Security\Authenticator;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'main_login';
    public const CART_RETURN_INTENT = 'cart';
    public const RETURN_INTENT_SESSION_KEY = 'app.login.return_intent';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $plaintextPassword = $request->request->get('password');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($plaintextPassword),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        $returnIntent = $session->get(self::RETURN_INTENT_SESSION_KEY);
        $session->remove(self::RETURN_INTENT_SESSION_KEY);

        if ($targetPath = $this->getTargetPath($session, $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        if (self::CART_RETURN_INTENT === $returnIntent) {
            return new RedirectResponse($this->urlGenerator->generate('main_cart_show', [
                '_locale' => $request->getLocale(),
            ]));
        }

        return new RedirectResponse($this->urlGenerator->generate('main_profile_index', [
            '_locale' => $request->getLocale(),
        ]));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return null;
    }
}
