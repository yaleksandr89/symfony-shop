<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Provider\Vk\VkUser;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class VkontakteAuthenticator extends AbstractOAuthAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $this->supportsOrdinaryCallback($request, 'connect_vkontakte_check');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('vkontakte_main');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var VkUser $vkUser */
                $vkUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
                $email = $vkUser->getEmail();

                return $this->oauthLoginHandler->handle(
                    OAuthProvider::Vkontakte,
                    $vkUser->getId(),
                    $email,
                    static fn (): User => UserFactory::createUserFromVk($vkUser)
                );
            })
        );
    }
}
