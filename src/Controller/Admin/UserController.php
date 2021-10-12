<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\User;
use App\Form\Admin\EditUserFormType;
use App\Form\DTO\EditUserModel;
use App\Form\Handler\UserFormHandler;
use App\Repository\UserRepository;
use App\Utils\Manager\CategoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/user", name="admin_user_")
 */
class UserController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var UserRepository
     */
    private UserRepository $userRepository;

    /**
     * @required
     * @param UserRepository $userRepository
     * @return UserController
     */
    public function setCategoryRepository(UserRepository $userRepository): UserController
    {
        $this->userRepository = $userRepository;
        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/list", name="list")
     */
    public function list(): Response
    {
        /** @var User $users */
        $users = $this->userRepository->findBy(['isDeleted' => false, 'isVerified' => true], ['id' => 'DESC']);

        return $this->render('admin/user/list.html.twig', [
            'users' => $users
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @Route("/add", name="add")
     * @param Request $request
     * @param UserFormHandler $userFormHandler
     * @param User|null $user
     * @return Response
     */
    public function edit(Request $request,UserFormHandler $userFormHandler, User $user = null): Response
    {
        $editUserModel = EditUserModel::makeFromUser($user);

        $form = $this->createForm(EditUserFormType::class, $editUserModel);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {

            if ($this->userRepository->findOneBy(['email' => $editUserModel->email])){
                $form
                    ->get('email')
                    ->addError(new FormError('This email is already registered'));
            }

            if ($form->isValid()){
                $user = $userFormHandler->processEditForm($editUserModel);
                $this->addFlash('success', 'Your changes were saved!');
                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Category $category
     * @param CategoryManager $categoryManager
     * @return Response
     */
    public function delete(Category $category, CategoryManager $categoryManager): Response
    {
        /*
        $id = $category->getId();
        $title = $category->getTitle();

        $categoryManager->remove($category);
        $this->addFlash('warning', "[Soft delete] The category (title: $title / ID: $id) was successfully deleted!");
*/
        return $this->redirectToRoute('admin_user_list');
    }
}
