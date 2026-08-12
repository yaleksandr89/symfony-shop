<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\Manager\ProductImageManager;
use App\Utils\Manager\ProductManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/product-image', name: 'admin_product_image_')]
class ProductImageController extends BaseAdminController
{
    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
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

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $productImageDir = $productManager->getProductImagesDir($product);
        $productImageManager->removeImageFromProduct($productImage, $productImageDir);
        $this->addTranslatedFlash('warning', 'flash.product_image.deleted', ['%id%' => $imgId]);

        return $this->redirectToRoute('admin_product_edit', [
            'id' => $product->getId(),
        ]);
    }
}
