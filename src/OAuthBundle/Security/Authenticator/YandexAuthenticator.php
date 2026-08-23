<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\Authenticator;

use App\Entity\User;
use App\OAuthBundle\Factory\UserFactory;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

class YandexAuthenticator extends AbstractOAuthAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return $this->supportsOrdinaryCallback($request, 'connect_yandex_check');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('yandex_main');
        $accessToken = $this->fetchAccessTokenFromProvider($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var YandexResourceOwner $yandexUser */
                $yandexUser = $this->fetchResourceOwnerFromProvider($client, $accessToken);
                $email = $yandexUser->getDefaultEmail();

                return $this->oauthLoginHandler->handle(
                    OAuthProvider::Yandex,
                    $yandexUser->getId(),
                    $email,
                    static fn (): User => UserFactory::createUserFromYandex($yandexUser, (string) $email)
                );
            })
        );
    }
}
