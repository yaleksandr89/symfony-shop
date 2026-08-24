<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GithubEnAuthenticator extends AbstractOAuthAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $this->supportsOrdinaryCallback($request, 'connect_github_en_check');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('github_en');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GithubResourceOwner $githubUser */
                $githubUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
                $email = $githubUser->getEmail();

                return $this->oauthLoginHandler->handle(
                    OAuthProvider::GithubEn,
                    (string) $githubUser->getId(),
                    $email,
                    static fn (): User => UserFactory::createUserFromGithub($githubUser)
                );
            })
        );
    }
}
