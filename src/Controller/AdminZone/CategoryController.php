<?php

declare(strict_types=1);

namespace App\Controller\AdminZone;

use App\Entity\Category;
use App\Form\DTO\EditCategoryModel;
use App\Form\EditCategoryFormType;
use App\Form\Handler\CategoryFormHandler;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/category", name="admin_category_")
 */
class CategoryController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var CategoryRepository
     */
    private CategoryRepository $categoryRepository;

    /**
     * @required
     * @param CategoryRepository $categoryRepository
     * @return CategoryController
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
        $categories = $this->categoryRepository->findBy([], ['id' => 'DESC']);

        return $this->render('admin/category/list.html.twig', [
            'categories' => $categories
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     * @Route("/add", name="add")
     * @param Request $request
     * @param CategoryFormHandler $categoryFormHandler
     * @param Category|null $category
     * @return Response
     */
    public function edit(Request $request, CategoryFormHandler $categoryFormHandler, Category $category = null): Response
    {
        $editCategoryModel = EditCategoryModel::makeFromCategory($category);

        $form = $this->createForm(EditCategoryFormType::class, $editCategoryModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $category = $categoryFormHandler->processEditForm($editCategoryModel);

            return $this->redirectToRoute('admin_category_list', ['id' => $category->getId()]);
        }

        return $this->render('admin/category/edit.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     * @param Category $category
     * @return Response
     */
    public function delete(Category $category): Response
    {
        // ...
    }
}
