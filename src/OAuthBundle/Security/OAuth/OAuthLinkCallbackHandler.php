<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthLinkIntentException;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final readonly class OAuthLinkCallbackHandler
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OAuthLinkIntentStore $intentStore,
        private readonly ClientRegistry $clientRegistry,
        private readonly OAuthAccountLinker $accountLinker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function handle(Request $request, OAuthProvider $provider): Response
    {
        /** @var Session $session */
        $session = $request->getSession();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $state = $request->query->all()['state'] ?? null;
        try {
            $this->intentStore->consume($user, $provider, is_string($state) ? $state : null);
        } catch (OAuthLinkIntentException $exception) {
            $session->remove(OAuth2Client::OAUTH2_SESSION_STATE_KEY);

            throw new AccessDeniedHttpException('Invalid OAuth link request.', $exception);
        }

        try {
            $client = $this->clientRegistry->getClient($provider->oauthClientName());
            $resourceOwner = $client->fetchUserFromToken($client->getAccessToken());
            $persistedUser = $this->accountLinker->link($user, $provider, $resourceOwner->getId());
            $this->synchronizeUser($token, $user, $persistedUser);
        } catch (Throwable) {
            try {
                $persistedUser = $this->accountLinker->recoverUser($user);
            } catch (Throwable) {
                throw new AccessDeniedHttpException('OAuth account is unavailable.');
            }
            $this->synchronizeUser($token, $user, $persistedUser);
            $session->getFlashBag()->add(
                'danger',
                $this->translator->trans('personal_account.social_group.oauth_link.failure')
            );

            return new RedirectResponse($this->urlGenerator->generate('main_profile_index'));
        } finally {
            $session->remove(OAuth2Client::OAUTH2_SESSION_STATE_KEY);
        }

        $session->getFlashBag()->add(
            'success',
            $this->translator->trans('The social network has been successfully linked.')
        );

        return new RedirectResponse($this->urlGenerator->generate('main_profile_index'));
    }

    private function synchronizeUser(TokenInterface $token, User $authenticatedUser, User $persistedUser): void
    {
        if ($this->tokenStorage->getToken() !== $token || $token->getUser() !== $authenticatedUser
            || $persistedUser->getId() !== $authenticatedUser->getId()
            || !$authenticatedUser->isEqualTo($persistedUser)
        ) {
            throw new AccessDeniedHttpException('OAuth account is unavailable.');
        }
        // EN: Keep the existing authentication and token; replace only a verified same-account user snapshot.
        // RU: Сохраняем аутентификацию и token; заменяем только проверенный снимок того же аккаунта.
        $token->setUser($persistedUser);
    }
}
