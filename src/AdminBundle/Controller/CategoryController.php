<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\AdminBundle\DTO\EditCategoryModel;
use App\AdminBundle\Form\EditCategoryFormType;
use App\AdminBundle\Handler\CategoryFormHandler;
use App\Catalog\Manager\CategoryManager;
use App\Catalog\Repository\CategoryRepository;
use App\Entity\Category;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

class CategoryController extends BaseAdminController
{
    private CategoryRepository $categoryRepository;

    #[Required]
    public function setCategoryRepository(CategoryRepository $categoryRepository): CategoryController
    {
        $this->categoryRepository = $categoryRepository;

        return $this;
    }

    public function list(): Response
    {
        $categories = $this->categoryRepository->findBy(['isDeleted' => false], ['id' => 'DESC']);

        return $this->render('@Admin/category/list.html.twig', [
            'categories' => $categories,
        ]);
    }

    public function edit(Request $request, CategoryFormHandler $categoryFormHandler, ?Category $category = null): Response
    {
        $editCategoryModel = EditCategoryModel::makeFromCategory($category);

        $form = $this->createForm(EditCategoryFormType::class, $editCategoryModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($redirect = $this->redirectIfUserIsUnverified()) {
                return $redirect;
            }

            $category = $categoryFormHandler->processEditForm($editCategoryModel);
            $this->addTranslatedFlash('success', 'flash.save_success');

            return $this->redirectToRoute('admin_category_edit', ['id' => $category->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addTranslatedFlash('warning', 'flash.form_invalid');
        }

        return $this->render('@Admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    public function delete(Request $request, Category $category, CategoryManager $categoryManager): Response
    {
        $id = $category->getId();
        $title = $category->getTitle();

        if (!$this->isCsrfTokenValid('delete_category_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->redirectIfUserIsUnverified()) {
            return $redirect;
        }

        $categoryManager->remove($category);
        $this->addTranslatedFlash('warning', 'flash.category.deleted', [
            '%title%' => $title,
            '%id%' => $id,
        ]);

        return $this->redirectToRoute('admin_category_list');
    }
}
