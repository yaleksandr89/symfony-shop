<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\Front\OAuthUnlinkFormType;
use App\Form\Front\ProfileEditFormType;
use App\Security\OAuth\OAuthIdentityAccessor;
use App\Security\OAuth\OAuthProvider;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileController extends AbstractController
{
    private Doctrine $doctrine;

    #[Required]
    public function setDoctrine(Doctrine $doctrine): ProfileController
    {
        $this->doctrine = $doctrine;

        return $this;
    }

    private EmailVerifier $emailVerifier;

    #[Required]
    public function setEmailVerifier(EmailVerifier $emailVerifier): ProfileController
    {
        $this->emailVerifier = $emailVerifier;

        return $this;
    }

    private UserRegisteredEmailSender $emailSender;

    #[Required]
    public function setEmailSender(UserRegisteredEmailSender $emailSender): ProfileController
    {
        $this->emailSender = $emailSender;

        return $this;
    }

    private TranslatorInterface $translator;

    #[Required]
    public function setTranslator(TranslatorInterface $translator): ProfileController
    {
        $this->translator = $translator;

        return $this;
    }

    #[Route('/profile', name: 'main_profile_index')]
    public function index(Request $request): Response
    {
        $sendEmail = false;

        if ($request->getSession()->get('resending_verify_email_link')) {
            $sendEmail = true;
            $request->getSession()->remove('resending_verify_email_link');
        }

        return $this->render('front/profile/index.html.twig', [
            'sendEmail' => $sendEmail,
        ]);
    }

    #[Route('/profile/edit', name: 'main_profile_edit')]
    public function edit(Request $request): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileEditFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->doctrine->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('main_profile_index');
        }

        return $this->render('front/profile/edit.html.twig', [
            'profileEditForm' => $form->createView(),
        ]);
    }

    #[Route('/profile/resending-verify-email-link', name: 'main_profile_resending_verify_email_link')]
    public function resendingVerifyEmailLink(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isVerified = $user->isVerified();

        if (!$isVerified) {
            $verifyEmailLink = $this
                ->emailVerifier
                ->generateEmailSignature('main_verify_email', $user);

            try {
                $this->emailSender->sendEmailToClient($user, $verifyEmailLink);
                $request->getSession()->set('resending_verify_email_link', true);
            } catch (TransportExceptionInterface) {
            }
        }

        return $this->redirectToRoute('main_profile_index');
    }

    #[Route('/profile/oauth/{provider}/unlink', name: 'main_profile_unlink_social_network', methods: ['GET', 'POST'])]
    public function unlinkSocialNetwork(
        Request $request,
        OAuthProvider $provider,
        OAuthIdentityAccessor $identityAccessor,
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
            $this->doctrine->getManager()->flush();

            $this->addFlash('success', $this->translator->trans('The social network has been successfully unlinked.'));

            return $this->redirectToRoute('main_profile_index');
        }

        return $this->render('front/profile/oauth_unlink.html.twig', [
            'oauthUnlinkForm' => $form->createView(),
            'providerLabel' => $this->translator->trans('personal_account.social_group.'.$provider->identityFamily()),
        ]);
    }
}
