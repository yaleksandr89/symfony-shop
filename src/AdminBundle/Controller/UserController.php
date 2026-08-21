<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\Account\Manager\UserManager;
use App\Account\Repository\UserRepository;
use App\AdminBundle\DTO\EditUserModel;
use App\AdminBundle\Form\EditUserFormType;
use App\AdminBundle\Handler\UserFormHandler;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

#[Route('/admin/user', name: 'admin_user_')]
class UserController extends BaseAdminController
{
    private UserRepository $userRepository;

    #[Required]
    public function setCategoryRepository(UserRepository $userRepository): UserController
    {
        $this->userRepository = $userRepository;

        return $this;
    }

    #[Route('/list', name: 'list')]
    public function list(): Response
    {
        /** @var User $users */
        $users = $this->userRepository->findBy(['isDeleted' => false], ['id' => 'DESC']);

        return $this->render('@Admin/user/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/edit/{id}', name: 'edit')]
    #[Route('/add', name: 'add')]
    public function edit(Request $request, UserFormHandler $userFormHandler, ?User $user = null): Response
    {
        $editUserModel = EditUserModel::makeFromUser($user);

        $form = $this->createForm(EditUserFormType::class, $editUserModel, ['user_repository' => $this->userRepository]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->checkTheAccessLevel()) {
                return $this->redirect($request->server->get('HTTP_REFERER'));
            }
            $user = $userFormHandler->processEditForm($editUserModel);
            $this->addTranslatedFlash('success', 'flash.save_success');

            return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addTranslatedFlash('warning', 'flash.form_invalid');
        }

        return $this->render('@Admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, UserManager $userManager): Response
    {
        $id = $user->getId();
        $fullName = $user->getFullName();

        if (!$this->isCsrfTokenValid('delete_user_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $userManager->remove($user);
        $this->addTranslatedFlash('warning', 'flash.user.deleted', [
            '%full_name%' => (string) $fullName,
            '%id%' => $id,
        ]);

        return $this->redirectToRoute('admin_user_list');
    }
}
