<?php

declare(strict_types=1);

namespace App\Security\Authenticator\Front;

use App\Entity\User;
use App\Event\UserLoggedInViaSocialNetworkEvent;
use App\Utils\Authenticator\CheckingUserSocialNetworkBeforeAuthorization;
use App\Utils\Factory\UserFactory;
use App\Utils\Generator\PasswordGenerator;
use App\Utils\Manager\UserManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use Psr\EventDispatcher\EventDispatcherInterface;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class GithubEnAuthenticator extends OAuth2Authenticator
{
    use CheckingUserSocialNetworkBeforeAuthorization;

    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserManager $userManager,
        private RouterInterface $router,
        private EventDispatcherInterface $eventDispatcher,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private TranslatorInterface $translator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // continue ONLY if the current ROUTE matches the check ROUTE
        return 'connect_github_en_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('github_en');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($request, $accessToken, $client) {
                /** @var Session $session */
                $session = $request->getSession();
                /** @var GithubResourceOwner $githubUser */
                $githubUser = $client->fetchUserFromToken($accessToken);
                $email = $githubUser->getEmail();

                // 1) have they logged in with Facebook before? Easy!
                $existingUser = $this->userManager->getRepository()->findOneBy(['githubId' => $githubUser->getId()]);

                if ($this->checkingUserSocialNetworkBeforeAuthorization($email)) {
                    $session
                        ->getFlashBag()
                        ->add(
                            'danger',
                            $this->translator->trans('You have already logged in to the site under the username of this social network')
                        );

                    return $this->security->getUser();
                }

                if ($existingUser) {
                    return $existingUser;
                }

                // 2) do we have a matching user by email?
                $user = $this->userManager->getRepository()->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = UserFactory::createUserFromGithub($githubUser);

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

                // 3) Maybe you just want to "register" them by creating
                // a User object
                $user->setGithubId((string) $githubUser->getId());
                $this->userManager->flush();

                return $user;
            })
        );
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
}
