<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Form\ProfileEditFormType;
use App\Account\Mailer\UserRegisteredEmailSender;
use App\Account\Security\Verifier\EmailVerifier;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

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

    #[Route('/profile', name: 'main_profile_index')]
    public function index(Request $request): Response
    {
        $sendEmail = false;

        if ($request->getSession()->get('resending_verify_email_link')) {
            $sendEmail = true;
            $request->getSession()->remove('resending_verify_email_link');
        }

        return $this->render('account/profile/index.html.twig', [
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

        return $this->render('account/profile/edit.html.twig', [
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
}
