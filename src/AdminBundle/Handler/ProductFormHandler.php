<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\AdminBundle\DTO\EditProductModel;
use App\Catalog\Image\FileSaver;
use App\Catalog\Image\FilesystemWorker;
use App\Catalog\Manager\ProductManager;
use App\Catalog\Repository\ProductRepository;
use App\Entity\Product;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

class ProductFormHandler
{
    private ProductRepository $productRepository;

    public function __construct(
        private ProductManager $productManager,
        private FileSaver $fileSaver,
        private FilesystemWorker $filesystemWorker,
        private PaginatorInterface $paginator,
        private FilterBuilderUpdater $filterBuilderUpdater,
        private LoggerInterface $logger,
    ) {
    }

    #[Required]
    public function setProductRepository(ProductRepository $productRepository): void
    {
        $this->productRepository = $productRepository;
    }

    public function processEditForm(FormInterface $form, EditProductModel $editProductModel): Product
    {
        $product = new Product();

        if ($editProductModel->id) {
            $product = $this->productRepository->find($editProductModel->id);
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
        $queryBuilder = $this->productRepository
            ->createQueryBuilder('p')
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
        $description = (!is_string($editProductModel->description))
            ? (string) $editProductModel->description
            : $editProductModel->description;

        $product->setTitle($editProductModel->title);
        $product->setPrice($editProductModel->price);
        $product->setQuantity($editProductModel->quantity);
        $product->setDescription($description);
        $product->setCategory($editProductModel->category);
        $product->setIsPublished($editProductModel->isPublished);
        $product->setIsDeleted($editProductModel->isDeleted);
        $product->setIsNew($editProductModel->isNew);
        $product->setIsOnSale($editProductModel->isOnSale);

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
