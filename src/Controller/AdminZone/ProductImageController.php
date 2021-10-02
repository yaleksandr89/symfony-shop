<?php

declare(strict_types=1);

namespace App\Controller\AdminZone;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\Manager\ProductImageManager;
use App\Utils\Manager\ProductManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/product-image", name="admin_product_image_")
 */
class ProductImageController extends AbstractController
{
    /**
     * @Route("/delete/{id}", name="delete")
     * @param ProductImage $productImage
     * @param ProductManager $productManager
     * @param ProductImageManager $productImageManager
     * @return Response
     */
    public function delete(ProductImage $productImage, ProductManager $productManager, ProductImageManager $productImageManager): Response
    {
        if (!isset($productImage)) {
            return $this->redirectToRoute('admin_product_list');
        }

        /** @var Product $product */
        $product = $productImage->getProduct();
        $productImageDir = $productManager->getProductImagesDir($product);
        $productImageManager->removeImageFromProduct($productImage, $productImageDir);

        return $this->redirectToRoute('admin_product_edit', [
            'id' => $product->getId(),
        ]);
    }
}
