<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\OAuthBundle\Security\OAuth\OAuthCallbackModeResolver;
use App\OAuthBundle\Security\OAuth\OAuthLoginHandler;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractOAuthAuthenticator extends OAuth2Authenticator
{
    private const EXTERNAL_FAILURE_MESSAGE = 'OAuth provider request failed.';

    private OAuthCallbackModeResolver $callbackModeResolver;

    public function __construct(
        protected readonly ClientRegistry $clientRegistry,
        protected readonly OAuthLoginHandler $oauthLoginHandler,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Required]
    public function setCallbackModeResolver(OAuthCallbackModeResolver $callbackModeResolver): void
    {
        $this->callbackModeResolver = $callbackModeResolver;
    }

    final public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        return new RedirectResponse($this->router->generate('main_profile_index'));
    }

    final public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add('danger', $this->translator->trans('oauth.authentication.failure'));

        return new RedirectResponse($this->router->generate('main_login', ['_locale' => $request->getLocale()]));
    }

    protected function supportsOrdinaryCallback(Request $request, string $routeName): bool
    {
        return $routeName === $request->attributes->get('_route')
            && $this->callbackModeResolver->useOrdinaryAuthenticator();
    }

    protected function fetchAccessTokenFromProvider(OAuth2ClientInterface $client): AccessToken
    {
        try {
            return $this->fetchAccessToken($client);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationServiceException(self::EXTERNAL_FAILURE_MESSAGE, 0, $exception);
        }
    }

    protected function fetchResourceOwnerFromProvider(
        OAuth2ClientInterface $client,
        AccessToken $accessToken,
    ): ResourceOwnerInterface {
        try {
            return $client->fetchUserFromToken($accessToken);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AuthenticationServiceException(self::EXTERNAL_FAILURE_MESSAGE, 0, $exception);
        }
    }
}
