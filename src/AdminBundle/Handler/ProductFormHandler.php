<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\AdminBundle\DTO\EditProductModel;
use App\AdminBundle\DTO\ProductFilterModel;
use App\Catalog\Image\FileSaver;
use App\Catalog\Image\FilesystemWorker;
use App\Catalog\Manager\ProductManager;
use App\Catalog\Repository\ProductRepository;
use App\Entity\Product;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterBuilderUpdater;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    ) {}

    #[Required]
    public function setProductRepository(ProductRepository $productRepository): void
    {
        $this->productRepository = $productRepository;
    }

    /** @param FormInterface<EditProductModel> $form */
    public function processEditForm(FormInterface $form, EditProductModel $editProductModel): Product
    {
        $product = new Product();

        if ($editProductModel->id) {
            $product = $this->productRepository->find($editProductModel->id) ?? throw new NotFoundHttpException('Product no longer exists.');
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

    /**
     * @param FormInterface<ProductFilterModel> $filterForm
     * @return PaginationInterface<int, Product>
     */
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

        $product->setTitle($editProductModel->title ?? throw new LogicException('Missing validated title field.'));
        $product->setPrice($editProductModel->price);
        $product->setQuantity($editProductModel->quantity ?? throw new LogicException('Missing validated quantity field.'));
        $product->setDescription($description);
        $product->setCategory($editProductModel->category);
        $product->setIsPublished($editProductModel->isPublished ?? throw new LogicException('Missing validated isPublished field.'));
        $product->setIsDeleted($editProductModel->isDeleted ?? throw new LogicException('Missing validated isDeleted field.'));
        $product->setIsNew($editProductModel->isNew ?? throw new LogicException('Missing validated isNew field.'));
        $product->setIsOnSale($editProductModel->isOnSale ?? throw new LogicException('Missing validated isOnSale field.'));

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
            // RU: Сбой логирования не должен менять результат операции.
            // EN: Logging is best-effort and must not change the operation outcome.
        }
    }
}
