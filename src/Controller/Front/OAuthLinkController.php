<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\Front\OAuthLinkFormType;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthLinkIntentStore;
use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Security\OAuth\OAuthProviderConfigurationException;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OAuthLinkController extends AbstractController
{
    #[Route('/profile/oauth/{provider}/link', name: 'main_profile_link_social_network', methods: ['GET', 'POST'])]
    public function link(
        Request $request,
        OAuthProvider $provider,
        OAuthProviderAvailability $availability,
        OAuthIdentityAccessor $identityAccessor,
        OAuthLinkIntentStore $intentStore,
        ClientRegistry $clientRegistry,
        TranslatorInterface $translator,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        if (!$provider->isImplemented()) {
            throw new NotFoundHttpException();
        }

        try {
            $availability->assertOperational($provider);
        } catch (OAuthProviderConfigurationException $exception) {
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(), $exception);
        }
        if (null !== $identityAccessor->getExternalId($user, $provider)) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(OAuthLinkFormType::class, null, [
            'action' => $this->generateUrl('main_profile_link_social_network', ['provider' => $provider->value]),
            'csrf_token_id' => 'oauth_link_'.$provider->value,
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $response = $clientRegistry->getClient($provider->oauthClientName())->redirect([], []);
            $state = $request->getSession()->get(OAuth2Client::OAUTH2_SESSION_STATE_KEY);
            if (!is_string($state) || '' === trim($state)) {
                $intentStore->clear();
                $request->getSession()->remove(OAuth2Client::OAUTH2_SESSION_STATE_KEY);

                throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'OAuth link could not be started.');
            }

            $intentStore->store($user, $provider, $state);

            return $response;
        }

        return $this->render('front/profile/oauth_link.html.twig', [
            'oauthLinkForm' => $form->createView(),
            'providerLabel' => $translator->trans('personal_account.social_group.'.$provider->identityFamily()),
        ]);
    }
}
