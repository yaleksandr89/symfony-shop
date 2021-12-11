<?php

declare(strict_types=1);

namespace App\Security\EntryPoint;

use App\Security\Authenticator\Admin\LoginFormAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AuthenticationAdminEntryPoint implements AuthenticationEntryPointInterface
{
    /**
     * @var UrlGeneratorInterface
     */
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param Request                      $request
     * @param AuthenticationException|null $authException
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse
    {
        $request->getSession()->getFlashBag()->add('warning', 'You have to login in order to access this page.');

        return new RedirectResponse($this->urlGenerator->generate(LoginFormAuthenticator::LOGIN_ROUTE));
    }
}
