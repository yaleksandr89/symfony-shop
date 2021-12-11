<?php

declare(strict_types=1);

namespace App\Form\Handler;

use App\Entity\Product;
use App\Form\DTO\EditProductModel;
use App\Utils\File\FileSaver;
use App\Utils\FileSystem\FilesystemWorker;
use App\Utils\Manager\ProductManager;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Lexik\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductFormHandler
{
    /** @var FileSaver */
    private FileSaver $fileSaver;

    /** @var ProductManager */
    private ProductManager $productManager;

    /** @var FilesystemWorker */
    private FilesystemWorker $filesystemWorker;

    /** @var PaginatorInterface */
    private PaginatorInterface $paginator;

    /** @var FilterBuilderUpdater */
    private FilterBuilderUpdater $filterBuilderUpdater;

    /**
     * @param ProductManager       $productManager
     * @param FileSaver            $fileSaver
     * @param FilesystemWorker     $filesystemWorker
     * @param PaginatorInterface   $paginator
     * @param FilterBuilderUpdater $filterBuilderUpdater
     */
    public function __construct(
        ProductManager $productManager,
        FileSaver $fileSaver,
        FilesystemWorker $filesystemWorker,
        PaginatorInterface $paginator,
        FilterBuilderUpdater $filterBuilderUpdater
    ) {
        $this->fileSaver = $fileSaver;
        $this->productManager = $productManager;
        $this->filesystemWorker = $filesystemWorker;
        $this->paginator = $paginator;
        $this->filterBuilderUpdater = $filterBuilderUpdater;
    }

    /**
     * @param FormInterface    $form
     * @param EditProductModel $editProductModel
     *
     * @return Product
     */
    public function processEditForm(FormInterface $form, EditProductModel $editProductModel): Product
    {
        $product = new Product();

        if ($editProductModel->id) {
            $product = $this->productManager->find($editProductModel->id);
        }

        $this->productManager->persist($product);

        $product = $this->fillingProductData($product, $editProductModel);
        $newImageFile = $form->get('newImage')->getData();
        $uploadsTempDir = $this->fileSaver->getUploadsTempDir();
        $uploadsFilename = $this->fileSaver->saveUploadedFileIntoTemp($newImageFile);

        $tempImageFilename = $newImageFile
            ? $uploadsFilename
            : null;

        $this->productManager->updateProductImages($product, $tempImageFilename);

        $this->productManager->flush();

        if ($tempImageFilename) {
            $this->filesystemWorker->remove($uploadsTempDir.'/'.$uploadsFilename);
            $this->filesystemWorker->removeFolderIfEmpty($uploadsTempDir);
        }

        return $product;
    }

    /**
     * @param Request $request
     * @param $filterForm
     *
     * @return PaginationInterface
     */
    public function processOrderFiltersForm(Request $request, FormInterface $filterForm): PaginationInterface
    {
        $queryBuilder = $this->productManager
            ->getQueryBuilder()
            ->leftJoin('p.category', 'c')
            ->where('p.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false);

        if ($filterForm->isSubmitted()) {
            $this->filterBuilderUpdater->addFilterConditions($filterForm, $queryBuilder);
        }

        return $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
        );
    }

    /**
     * @param Product          $product
     * @param EditProductModel $editProductModel
     *
     * @return Product
     */
    private function fillingProductData(Product $product, EditProductModel $editProductModel): Product
    {
        $title = (!is_string($editProductModel->title))
            ? (string) $editProductModel->title
            : $editProductModel->title;

        $price = (!is_string($editProductModel->price))
            ? (string) $editProductModel->price
            : $editProductModel->price;

        $quantity = (!is_int($editProductModel->quantity))
            ? (int) $editProductModel->quantity
            : $editProductModel->quantity;

        $description = (!is_string($editProductModel->description))
            ? (string) $editProductModel->description
            : $editProductModel->description;

        $category = $editProductModel->category;

        $isPublished = (!is_bool($editProductModel->isPublished))
            ? (bool) $editProductModel->isPublished
            : $editProductModel->isPublished;

        $isDeleted = (!is_bool($editProductModel->isDeleted))
            ? (bool) $editProductModel->isDeleted
            : $editProductModel->isDeleted;

        $product->setTitle($title);
        $product->setPrice($price);
        $product->setQuantity($quantity);
        $product->setDescription($description);
        $product->setCategory($category);
        $product->setIsPublished($isPublished);
        $product->setIsDeleted($isDeleted);

        return $product;
    }
}
