<?php

declare(strict_types=1);

namespace App\Security\Authenticator\Front;

use App\Entity\User;
use App\Event\UserLoggedInViaSocialNetworkEvent;
use App\Security\OAuth\OAuthCallbackModeResolver;
use App\Security\OAuth\OAuthUserResolver;
use App\Utils\Authenticator\CheckingUserSocialNetworkBeforeAuthorization;
use App\Utils\Factory\UserFactory;
use App\Utils\Generator\PasswordGenerator;
use App\Utils\Manager\UserManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Yaleksandr\OAuth2\Client\Provider\YandexResourceOwner;

class YandexAuthenticator extends OAuth2Authenticator
{
    use CheckingUserSocialNetworkBeforeAuthorization;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserManager $userManager,
        private OAuthUserResolver $oauthUserResolver,
        private RouterInterface $router,
        private EventDispatcherInterface $eventDispatcher,
        private VerifyEmailHelperInterface $verifyEmailHelper,
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
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_yandex_check' === $request->attributes->get('_route')
            && $this->callbackModeResolver->useOrdinaryAuthenticator();
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('yandex_main');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($request, $accessToken, $client) {
                /** @var Session $session */
                $session = $request->getSession();
                /** @var YandexResourceOwner $yandexUser */
                $yandexUser = $client->fetchUserFromToken($accessToken);
                $email = trim($yandexUser->getDefaultEmail() ?? '');

                if ('' === $email) {
                    throw new CustomUserMessageAuthenticationException('Unable to authenticate with this provider.');
                }

                if ($this->checkingUserSocialNetworkBeforeAuthorization($email)) {
                    $session
                        ->getFlashBag()
                        ->add(
                            'danger',
                            $this->translator->trans('You have already logged in to the site under the username of this social network')
                        );

                    return $this->security->getUser();
                }

                $resolution = $this->oauthUserResolver->resolve(
                    OAuthUserResolver::PROVIDER_YANDEX,
                    $yandexUser->getId(),
                    $email,
                    static fn (): User => UserFactory::createUserFromYandex($yandexUser, $email)
                );
                $user = $resolution->user();

                if ($resolution->isNewUser()) {
                    $plainPassword = PasswordGenerator::generatePassword(15);
                    $this->userManager->encodePassword($user, $plainPassword);

                    $this->userManager->persist($user);
                    $verifyEmail = $this->getDataForVerifyEmail($user);

                    $event = new UserLoggedInViaSocialNetworkEvent($user, $plainPassword, $verifyEmail);
                    $this->eventDispatcher->dispatch($event);

                    $session
                        ->getFlashBag()
                        ->add(
                            'success',
                            $this->translator->trans('An email has been sent. Please check inbox to find password and verified your email')
                        );
                }

                if ($resolution->requiresFlush()) {
                    $session
                        ->getFlashBag()
                        ->add(
                            'success',
                            $this->translator->trans('The social network has been successfully linked.')
                        );
                    $this->userManager->flush();
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('main_profile_index');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        // return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    private function getDataForVerifyEmail(User $user): array
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            'main_verify_email',
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => (string) $user->getId()]
        );

        return [
            'signedUrl' => $signatureComponents->getSignedUrl(),
            'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
            'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
        ];
    }
}
