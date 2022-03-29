<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\Front\ProfileEditFormType;
use App\Security\Verifier\EmailVerifier;
use App\Utils\Mailer\Sender\UserRegisteredEmailSender;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    /** @var TranslatorInterface */
    private $translator;

    /**
     * @required
     *
     * @param TranslatorInterface $translator
     *
     * @return ProfileController
     */
    public function setTranslator(TranslatorInterface $translator): ProfileController
    {
        $this->translator = $translator;

        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/profile", name="main_profile_index")
     *
     * @param Request $request
     *
     * @return Response
     */
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
     * @param Request $request
     *
     * @return Response
     */
    public function resendingVerifyEmailLink(Request $request): Response
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

        $request->getSession()->set('resending_verify_email_link', true);

        return $this->redirectToRoute('main_profile_index');
    }

    /**
     * @Route("/profile/unlink_social_network/{socialName}", name="main_profile_unlink_social_network")
     *
     * @param string $socialName
     *
     * @return RedirectResponse
     */
    public function unlinkSocialNetwork(string $socialName): RedirectResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }
        $em = $this->doctrine->getManager();

        $nameMethod = 'set'.ucfirst($socialName).'Id';
        $user->$nameMethod(null);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $this->translator->trans('The social network has been successfully unlinked.'));

        return $this->redirectToRoute('main_profile_index');
    }
}
