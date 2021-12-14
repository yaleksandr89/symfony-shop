<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\Front\ProfileEditFormType;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    // >>> Autowiring
    /** @var Doctrine */
    private Doctrine $doctrine;

    /**
     * @required
     *
     * @param Doctrine $doctrine
     *
     * @return ProfileController
     */
    public function setDoctrine(Doctrine $doctrine): ProfileController
    {
        $this->doctrine = $doctrine;

        return $this;
    }

    /** @var EmailVerifier */
    private EmailVerifier $emailVerifier;

    /**
     * @required
     *
     * @param EmailVerifier $emailVerifier
     *
     * @return ProfileController
     */
    public function setEmailVerifier(EmailVerifier $emailVerifier): ProfileController
    {
        $this->emailVerifier = $emailVerifier;

        return $this;
    }

    /** @var UserRegisteredEmailSender */
    private UserRegisteredEmailSender $emailSender;

    /**
     * @required
     *
     * @param UserRegisteredEmailSender $emailSender
     *
     * @return ProfileController
     */
    public function setEmailSender(UserRegisteredEmailSender $emailSender): ProfileController
    {
        $this->emailSender = $emailSender;

        return $this;
    }

    /** @var SessionInterface */
    private SessionInterface $session;

    /**
     * @required
     *
     * @param SessionInterface $session
     *
     * @return ProfileController
     */
    public function setSession(SessionInterface $session): ProfileController
    {
        $this->session = $session;

        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/profile", name="main_profile_index")
     *
     * @return Response
     */
    public function index(): Response
    {
        $sendEmail = false;

        if ($this->session->get('resending_verify_email_link')) {
            $sendEmail = true;
            $this->session->remove('resending_verify_email_link');
        }

        return $this->render('front/profile/index.html.twig', [
            'sendEmail' => $sendEmail,
        ]);
    }

    /**
     * @Route("/profile/edit", name="main_profile_edit")
     *
     * @param Request $request
     *
     * @return Response
     */
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

    /**
     * @Route("/profile/resending-verify-email-link", name="main_profile_resending_verify_email_link")
     *
     * @return Response
     */
    public function resendingVerifyEmailLink(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isVerified = $user->isVerified();

        if (!$isVerified) {
            $verifyEmailLink = $this
                ->emailVerifier
                ->generateEmailSignature('main_verify_email', $user);
            $this->emailSender->sendEmailToClient($user, $verifyEmailLink);
        }

        $this->session->set('resending_verify_email_link', true);

        return $this->redirectToRoute('main_profile_index');
    }
}
