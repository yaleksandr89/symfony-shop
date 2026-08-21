<?php

declare(strict_types=1);

namespace App\OAuthBundle\Controller;

use App\Entity\User;
use App\OAuthBundle\Form\OAuthUnlinkFormType;
use App\OAuthBundle\Security\OAuth\OAuthIdentityAccessor;
use App\OAuthBundle\Security\OAuth\OAuthProvider;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OAuthUnlinkController extends AbstractController
{
    public function unlinkSocialNetwork(
        Request $request,
        OAuthProvider $provider,
        OAuthIdentityAccessor $identityAccessor,
        Doctrine $doctrine,
        TranslatorInterface $translator,
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User || !$provider->isCurrentIdentityProvider()) {
            throw new NotFoundHttpException('User not found');
        }

        if (null === $identityAccessor->getExternalId($user, $provider)) {
            throw new NotFoundHttpException('OAuth identity not found');
        }

        $form = $this->createForm(OAuthUnlinkFormType::class, null, [
            'action' => $this->generateUrl('main_profile_unlink_social_network', ['provider' => $provider->value]),
            'csrf_token_id' => 'oauth_unlink_'.$provider->value,
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $identityAccessor->unlink($user, $provider);
            $doctrine->getManager()->flush();

            $this->addFlash('success', $translator->trans('The social network has been successfully unlinked.'));

            return $this->redirectToRoute('main_profile_index');
        }

        return $this->render('@OAuth/profile/oauth_unlink.html.twig', [
            'oauthUnlinkForm' => $form->createView(),
            'providerLabel' => $translator->trans('personal_account.social_group.'.$provider->identityFamily()),
        ]);
    }
}
