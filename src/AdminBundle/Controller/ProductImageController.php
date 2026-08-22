<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use App\Catalog\Manager\ProductImageManager;
use App\Catalog\Manager\ProductManager;
use App\Entity\Product;
use App\Entity\ProductImage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductImageController extends BaseAdminController
{
    public function delete(
        Request $request,
        ProductImage $productImage,
        ProductManager $productManager,
        ProductImageManager $productImageManager,
    ): Response {
        /** @var Product $product */
        $product = $productImage->getProduct();
        $imgId = $productImage->getId();

        if (!$this->isCsrfTokenValid('delete_product_image_'.$imgId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($redirect = $this->redirectIfUserIsUnverified()) {
            return $redirect;
        }

        $productImageDir = $productManager->getProductImagesDir($product);
        $productImageManager->removeImageFromProduct($productImage, $productImageDir);
        $this->addTranslatedFlash('warning', 'flash.product_image.deleted', ['%id%' => $imgId]);

        return $this->redirectToRoute('admin_product_edit', [
            'id' => $product->getId(),
        ]);
    }
}
