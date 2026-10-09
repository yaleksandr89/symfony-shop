<?php

declare(strict_types=1);

namespace App\OAuthBundle\Controller;

use App\Entity\User;
use App\OAuthBundle\Form\OAuthUnlinkFormType;
use App\OAuthBundle\Security\OAuth\Exception\OAuthIdentityConflictException;
use App\OAuthBundle\Security\OAuth\OAuthAccountLinker;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OAuthUnlinkController extends AbstractController
{
    public function unlinkSocialNetwork(
        Request $request,
        OAuthProvider $provider,
        OAuthIdentityAccessor $identityAccessor,
        OAuthAccountLinker $accountLinker,
        TranslatorInterface $translator,
        TokenStorageInterface $tokenStorage,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User || !$provider->isCurrentIdentityProvider()) {
            throw $this->createNotFoundException('User not found');
        }

        $expectedId = $identityAccessor->getExternalId($user, $provider);
        if (null === $expectedId) {
            throw $this->createNotFoundException('OAuth identity not found');
        }

        $form = $this->createForm(OAuthUnlinkFormType::class, null, [
            'action' => $this->generateUrl('main_profile_unlink_social_network', ['provider' => $provider->value]),
            'csrf_token_id' => 'oauth_unlink_'.$provider->value,
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $persistedUser = $accountLinker->unlink($user, $provider, $expectedId);
                $this->synchronizeUser($tokenStorage, $user, $persistedUser);
            } catch (OAuthIdentityConflictException) {
                $this->synchronizeUser($tokenStorage, $user, $accountLinker->recoverUser($user));
                $this->addFlash('danger', $translator->trans('Denied'));

                return $this->redirectToRoute('main_profile_index');
            }

            $this->addFlash('success', $translator->trans('The social network has been successfully unlinked.'));

            return $this->redirectToRoute('main_profile_index');
        }

        return $this->render('@OAuth/profile/oauth_unlink.html.twig', [
            'oauthUnlinkForm' => $form->createView(),
            'providerLabel' => $translator->trans('personal_account.social_group.'.$provider->identityFamily()),
        ]);
    }

    private function synchronizeUser(TokenStorageInterface $tokenStorage, User $authenticatedUser, User $persistedUser): void
    {
        $token = $tokenStorage->getToken();
        if ($token?->getUser() !== $authenticatedUser || $persistedUser->getId() !== $authenticatedUser->getId()
            || !$authenticatedUser->isEqualTo($persistedUser)
        ) {
            throw new AccessDeniedHttpException('OAuth account is unavailable.');
        }
        $token->setUser($persistedUser);
    }
}
