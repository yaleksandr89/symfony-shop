<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductImage;
use App\Utils\Manager\ProductImageManager;
use App\Utils\Manager\ProductManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/product-image", name="admin_product_image_")
 */
class ProductImageController extends BaseAdminController
{
    /**
     * @Route("/delete/{id}", name="delete")
     *
     * @param Request             $request
     * @param ProductImage        $productImage
     * @param ProductManager      $productManager
     * @param ProductImageManager $productImageManager
     *
     * @return Response
     */
    public function delete(Request $request, ProductImage $productImage, ProductManager $productManager, ProductImageManager $productImageManager): Response
    {
        if (!isset($productImage)) {
            return $this->redirectToRoute('admin_product_list');
        }

        /** @var Product $product */
        $product = $productImage->getProduct();

        if (!$this->checkTheAccessLevel()) {
            return $this->redirect($request->server->get('HTTP_REFERER'));
        }

        $productImageDir = $productManager->getProductImagesDir($product);
        $imgId = $productImage->getId();

        $productImageManager->removeImageFromProduct($productImage, $productImageDir);
        $this->addFlash('warning', "The image (ID: $imgId) was successfully deleted!");

        return $this->redirectToRoute('admin_product_edit', [
            'id' => $product->getId(),
        ]);
    }
}
