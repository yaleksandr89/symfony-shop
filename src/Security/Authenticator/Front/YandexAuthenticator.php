<?php

declare(strict_types=1);

namespace App\Security\Authenticator\Front;

use Aego\OAuth2\Client\Provider\YandexResourceOwner;
use App\Entity\User;
use App\Event\UserLoggedInViaSocialNetworkEvent;
use App\Utils\Factory\UserFactory;
use App\Utils\Generator\PasswordGenerator;
use App\Utils\Manager\UserManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class YandexAuthenticator extends OAuth2Authenticator
{
    /** @var ClientRegistry */
    private $clientRegistry;

    /** @var RouterInterface */
    private $router;

    /** @var UserManager */
    private UserManager $userManager;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var VerifyEmailHelperInterface
     */
    private VerifyEmailHelperInterface $verifyEmailHelper;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param ClientRegistry             $clientRegistry
     * @param UserManager                $userManager
     * @param RouterInterface            $router
     * @param EventDispatcherInterface   $eventDispatcher
     * @param VerifyEmailHelperInterface $helper
     * @param TranslatorInterface        $translator
     */
    public function __construct(
        ClientRegistry $clientRegistry,
        UserManager $userManager,
        RouterInterface $router,
        EventDispatcherInterface $eventDispatcher,
        VerifyEmailHelperInterface $helper,
        TranslatorInterface $translator
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->router = $router;
        $this->userManager = $userManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->verifyEmailHelper = $helper;
        $this->translator = $translator;
    }

    /**
     * @param Request $request
     *
     * @return bool|null
     */
    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_yandex_check' === $request->attributes->get('_route');
    }

    /**
     * @param Request $request
     *
     * @return Passport
     */
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('yandex_main');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($request, $accessToken, $client) {
                /** @var YandexResourceOwner $yandexUser */
                $yandexUser = $client->fetchUserFromToken($accessToken);

                $email = $yandexUser->getEmail();
                $existingUser = $this->userManager->getRepository()->findOneBy(['yandexId' => $yandexUser->getId()]);

                if ($existingUser) {
                    return $existingUser;
                }

                $user = $this->userManager->getRepository()->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = UserFactory::createUserFromYandex($yandexUser);

                    $plainPassword = PasswordGenerator::generatePassword(15);
                    $this->userManager->encodePassword($user, $plainPassword);

                    $this->userManager->persist($user);
                    $verifyEmail = $this->getDataForVerifyEmail($user);

                    $event = new UserLoggedInViaSocialNetworkEvent($user, $plainPassword, $verifyEmail);
                    $this->eventDispatcher->dispatch($event);

                    $request->getSession()->getFlashBag()->add('success', $this->translator->trans('An email has been sent. Please check inbox to find password and verified your email'));
                }

                // 3) Maybe you just want to "register" them by creating
                // a User object
                $user->setYandexId($yandexUser->getId());
                $this->userManager->flush();

                return $user;
            })
        );
    }

    /**
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $firewallName
     *
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('main_profile_index');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        // return null;
    }

    /**
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return Response
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * @param User $user
     *
     * @return array
     */
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
