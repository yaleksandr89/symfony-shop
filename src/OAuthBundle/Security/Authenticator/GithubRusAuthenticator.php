<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Security\OAuth\OAuthCallbackModeResolver;
use App\OAuthBundle\Security\OAuth\OAuthLoginHandler;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

class GithubRusAuthenticator extends AbstractOAuthAuthenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private OAuthLoginHandler $oauthLoginHandler,
        private RouterInterface $router,
        private TranslatorInterface $translator,
    ) {
    }

    private OAuthCallbackModeResolver $callbackModeResolver;

    #[Required]
    public function setCallbackModeResolver(OAuthCallbackModeResolver $callbackModeResolver): void
    {
        $this->callbackModeResolver = $callbackModeResolver;
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_github_ru_check' === $request->attributes->get('_route')
            && $this->callbackModeResolver->useOrdinaryAuthenticator();
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('github_ru');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GithubResourceOwner $githubUser */
                $githubUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
                $githubUserEmail = $githubUser->getEmail();

                return $this->oauthLoginHandler->handle(
                    OAuthProvider::GithubRus,
                    (string) $githubUser->getId(),
                    $githubUserEmail,
                    static fn (): User => UserFactory::createUserFromGithub($githubUser)
                );
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        $targetUrl = $this->router->generate('main_profile_index');

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        /** @var Session $session */
        $session = $request->getSession();
        $session->getFlashBag()->add(
            'danger',
            $this->translator->trans('oauth.authentication.failure')
        );

        return new RedirectResponse($this->router->generate('main_login', ['_locale' => $request->getLocale()]));
    }
}
