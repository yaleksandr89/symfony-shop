<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Provider\Linkedin\LinkedinUser;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class LinkedinAuthenticator extends AbstractOAuthAuthenticator
{
    public function supports(Request $request): bool
    {
        return $this->supportsOrdinaryCallback($request, 'connect_linkedin_check');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('linkedin_main');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
            /** @var LinkedinUser $linkedinUser */
            $linkedinUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
            $email = $linkedinUser->getEmail();

            return $this->oauthLoginHandler->handle(
                OAuthProvider::Linkedin,
                $linkedinUser->getId(),
                $email,
                static fn (): User => UserFactory::createUserFromLinkedin($linkedinUser, (string) $email),
            );
        }));
    }
}
