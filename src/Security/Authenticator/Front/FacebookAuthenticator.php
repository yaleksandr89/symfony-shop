<?php

declare(strict_types=1);

namespace App\Security\Authenticator\Front;

use App\Entity\User;
use App\Utils\Factory\UserFactory;
use App\Utils\Generator\PasswordGenerator;
use App\Utils\Manager\UserManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Client\Provider\FacebookClient;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use League\OAuth2\Client\Provider\FacebookUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class FacebookAuthenticator extends OAuth2Authenticator
{
    /**
     * @var ClientRegistry
     */
    private $clientRegistry;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(
        ClientRegistry $clientRegistry,
        UserManager $userManager,
        RouterInterface $router,
        SessionInterface $session,
    ) {
        $this->clientRegistry = $clientRegistry;
        $this->router = $router;
        $this->userManager = $userManager;
        $this->session = $session;
    }

    /**
     * @param Request $request
     * @return bool|null
     */
    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_facebook_check' === $request->attributes->get('_route');
    }

    /**
     * @param Request $request
     * @return PassportInterface
     */
    public function authenticate(Request $request): PassportInterface
    {
        $client = $this->clientRegistry->getClient('facebook_main');
        $accessToken = $this->fetchAccessToken($client);



        /** @var FacebookUser $facebookUser */
        $facebookUser = $client->fetchUserFromToken($accessToken);

        $email = $facebookUser->getEmail();

        // 1) have they logged in with Facebook before? Easy!
        $existingUser = $this->userManager->getRepository()->findOneBy(['facebookId' => $facebookUser->getId()]);

        if ($existingUser) {
            return $existingUser;
        }

        // 2) do we have a matching user by email?
        $user = $this->userManager->getRepository()->findOneBy(['email' => $email]);

        if (!$user) {
            $user = UserFactory::createUserFromFacebookUser($facebookUser);
            $user->setEmail($email);

            $plainPassword = PasswordGenerator::generatePassword(15);
            $this->userManager->encodePassword($user, $plainPassword);

            $this->session->getFlashBag()->add('success', 'An email has been sent. Please check your inbox to find password');

//            $this->userManager->persist($user);
//            $this->userManager->flush();
        }


//        return new SelfValidatingPassport(
//            new UserBadge($accessToken->getToken(), static function () use ($accessToken, $client) {
//
//
//            })
//        );
    }

    /**
     * @param Request $request
     * @param TokenInterface $token
     * @param string $providerKey
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $providerKey): RedirectResponse
    {
        // change "app_homepage" to some route in your app
        $targetUrl = $this->router->generate('main_profile_index');

        return new RedirectResponse($targetUrl);

        // or, on success, let the request continue to be handled by the controller
        //return null;
    }

    /**
     * @param Request $request
     * @param AuthenticationException $exception
     * @return Response
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}
