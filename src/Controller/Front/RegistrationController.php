<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\Front\RegistrationFormType;
use App\Messenger\Message\Event\EventUserRegisteredEvent;
use App\Repository\UserRepository;
use App\Security\Verifier\EmailVerifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

//use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class RegistrationController extends AbstractController
{
    /**
     * @var EmailVerifier
     */
    private EmailVerifier $emailVerifier;

    /**
     * @param EmailVerifier $emailVerifier
     */
    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }

    /**
     * @Route("/registration", name="main_registration")
     *
     * @param Request                     $request
     * @param UserPasswordHasherInterface $passwordEncoder
     * @param MessageBusInterface         $messageBus
     *
     * @return Response
     */
    public function registration(Request $request, UserPasswordHasherInterface $passwordEncoder, MessageBusInterface $messageBus): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('main_profile_index');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $passwordEncoder->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            $event = new EventUserRegisteredEvent($user->getId());
            $messageBus->dispatch($event);

            // do anything else you need here, like send an email
            $this->addFlash('success', 'An email has been sent. Please check your inbox to complete registration.');

            return $this->redirectToRoute('main_homepage');
        }

        return $this->render('front/security/registration.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/verify/email", name="main_verify_email")
     *
     * @param Request        $request
     * @param UserRepository $userRepository
     *
     * @return Response
     */
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->get('id');

        if (null === $id) {
            return $this->redirectToRoute('main_registration');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('main_registration');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request->getUri(), $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('warning', $exception->getReason());

            return $this->redirectToRoute('main_homepage');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('main_homepage');
    }
}
