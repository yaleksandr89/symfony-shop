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
use Psr\Log\LoggerInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class ProductFormHandler
{
    public function __construct(
        private ProductManager $productManager,
        private FileSaver $fileSaver,
        private FilesystemWorker $filesystemWorker,
        private PaginatorInterface $paginator,
        private FilterBuilderUpdater $filterBuilderUpdater,
        private LoggerInterface $logger,
    ) {
    }

    public function processEditForm(FormInterface $form, EditProductModel $editProductModel): Product
    {
        $product = new Product();

        if ($editProductModel->id) {
            $product = $this->productManager->find($editProductModel->id);
        }

        $product = $this->fillingProductData($product, $editProductModel);
        $newImageFile = $form->get('newImage')->getData();
        $tempImageFilename = $this->fileSaver->saveUploadedFileIntoTemp($newImageFile);

        if (null === $tempImageFilename) {
            return $this->productManager->saveProduct($product);
        }

        try {
            return $this->productManager->saveProduct($product, $tempImageFilename);
        } finally {
            $this->cleanupTempImage($tempImageFilename);
        }
    }

    public function processOrderFiltersForm(Request $request, FormInterface $filterForm): PaginationInterface
    {
        $queryBuilder = $this->productManager
            ->getQueryBuilder()
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.isDeleted = :isDeleted')
            ->setParameter('isDeleted', false);

        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $this->filterBuilderUpdater->addFilterConditions($filterForm, $queryBuilder);
        }

        return $this->paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
        );
    }

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

        $isNew = (!is_bool($editProductModel->isNew))
            ? (bool) $editProductModel->isNew
            : $editProductModel->isNew;

        $isOnSale = (!is_bool($editProductModel->isOnSale))
            ? (bool) $editProductModel->isOnSale
            : $editProductModel->isOnSale;

        $product->setTitle($title);
        $product->setPrice($price);
        $product->setQuantity($quantity);
        $product->setDescription($description);
        $product->setCategory($category);
        $product->setIsPublished($isPublished);
        $product->setIsDeleted($isDeleted);
        $product->setIsNew($isNew);
        $product->setIsOnSale($isOnSale);

        return $product;
    }

    private function cleanupTempImage(string $tempImageFilename): void
    {
        $uploadsTempDir = $this->fileSaver->getUploadsTempDir();
        $tempImagePath = $this->filesystemWorker->generatePathToFile($uploadsTempDir, $tempImageFilename);

        try {
            $this->filesystemWorker->remove($tempImagePath);
        } catch (Throwable $exception) {
            $this->logCleanupFailure('Unable to remove a staged product image.', $exception);
        }

        try {
            $this->filesystemWorker->removeFolderIfEmpty($uploadsTempDir);
        } catch (Throwable $exception) {
            $this->logCleanupFailure('Unable to remove the empty product image staging directory.', $exception);
        }
    }

    private function logCleanupFailure(string $message, Throwable $exception): void
    {
        try {
            $this->logger->warning($message, [
                'exception_class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Logging is best-effort and must not change the operation outcome.
        }
    }
}
