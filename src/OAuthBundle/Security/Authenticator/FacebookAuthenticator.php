<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Provider\Facebook\FacebookUser;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class FacebookAuthenticator extends AbstractOAuthAuthenticator
{
    public function supports(Request $request): bool
    {
        return $this->supportsOrdinaryCallback($request, 'connect_facebook_check');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('facebook_main');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
            /** @var FacebookUser $facebookUser */
            $facebookUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
            $email = $facebookUser->getEmail();

            return $this->oauthLoginHandler->handle(
                OAuthProvider::Facebook,
                $facebookUser->getId(),
                $email,
                static fn (): User => UserFactory::createUserFromFacebook($facebookUser, (string) $email),
            );
        }));
    }
}
