<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Form\Admin\EditCategoryFormType;
use App\Form\DTO\EditCategoryModel;
use App\Form\Handler\CategoryFormHandler;
use App\Repository\CategoryRepository;
use App\Utils\Manager\CategoryManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/category", name="admin_category_")
 */
class CategoryController extends BaseAdminController
{
    // >>> Autowiring

    private CategoryRepository $categoryRepository;

    /**
     * @required
     */
    public function setCategoryRepository(CategoryRepository $categoryRepository): CategoryController
    {
        $this->categoryRepository = $categoryRepository;

        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/list", name="list")
     */
    public function list(): Response
    {
        $categories = $this->categoryRepository->findBy(['isDeleted' => false], ['id' => 'DESC']);

        return $this->render('admin/category/list.html.twig', [
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @Route("/add", name="add")
     * @{убрать}IsGranted("CAN_ADMIN_EDIT", subject="category") - если требуется редирект в случае если пользователь isVerified = false
     * Используется избиратель src/Security/Voters/AdminOrderEditVoter
     */
    public function edit(Request $request, CategoryFormHandler $categoryFormHandler, Category $category = null): Response
    {
        $editCategoryModel = EditCategoryModel::makeFromCategory($category);

        $form = $this->createForm(EditCategoryFormType::class, $editCategoryModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->checkTheAccessLevel()) {
                return $this->redirect($request->server->get('HTTP_REFERER'));
            }

            $category = $categoryFormHandler->processEditForm($editCategoryModel);
            $this->addFlash('success', 'Your changes were saved!');

            return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('warning', 'Something went wrong. Please check!');
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     */
    public function delete(Request $request, Category $category, CategoryManager $categoryManager): Response
    {
        $id = $category->getId();
        $title = $category->getTitle();

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $categoryManager->remove($category);
        $this->addFlash('warning', "[Soft delete] The category (title: $title / ID: $id) was successfully deleted!");

        return $this->redirectToRoute('admin_category_list');
    }
}
