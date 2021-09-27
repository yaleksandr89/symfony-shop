<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ProfileEditFormType;
use Doctrine\Persistence\ManagerRegistry as Doctrine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var Doctrine
     */
    private Doctrine $doctrine;

    /**
     * @required
     * @param Doctrine $doctrine
     * @return ProfileController
     */
    public function setDoctrine(Doctrine $doctrine): ProfileController
    {
        $this->doctrine = $doctrine;
        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/profile", name="main_profile_index")
     * @return Response
     */
    public function index(): Response
    {
        return $this->render('front/profile/index.html.twig', [
            'controller_name' => 'ProfileController',
        ]);
    }

    /**
     * @Route("/profile/edit", name="main_profile_edit")
     * @param Request $request
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
}
