<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DefaultController extends AbstractController
{
    // >>> Autowiring
    /**
     * @var UrlGeneratorInterface
     */
    private UrlGeneratorInterface $urlGenerator;

    /**
     * @required
     * @param UrlGeneratorInterface $urlGenerator
     * @return DefaultController
     */
    public function setUrlGenerator(UrlGeneratorInterface $urlGenerator): DefaultController
    {
        $this->urlGenerator = $urlGenerator;
        return $this;
    }
    // Autowiring <<<

    /**
     * @Route("/", name="main_homepage")
     * @param Request $request
     * @param CategoryRepository $categoryRepository
     * @return Response
     */
    public function index(Request $request, CategoryRepository $categoryRepository): Response
    {
        $preparedListCategory = [];
        $baseProductImagDir = $this->getParameter('product_images_url');

        /** @var Category $category */
        foreach ($categoryRepository->findActiveCategoryWithJoinProduct() as $category) {
            $preparedProduct = [];
            /** @var Product $item */
            foreach ($category->getProducts()->toArray() as $item) {
                if (false === $item->getIsDeleted() && true === $item->getIsPublished() && false !== $item->getProductImages()->first()) {
                    $preparedProduct[] = $item;
                }
            }
            if (count($preparedProduct) > 0) {
                /** @var Product $randomProduct */
                $productArrKey = array_rand($preparedProduct, 1);
                $randomProduct = $preparedProduct[$productArrKey];

                /** @var ProductImage $productImg */
                $productImg = $randomProduct->getProductImages()->first();
                $randProductImg = $request->getUriForPath($baseProductImagDir) . "/{$randomProduct->getId()}/{$productImg->getFilenameBig()}";

                $preparedListCategory[] = [
                    'title' => $category->getTitle(),
                    'url' => $this->urlGenerator->generate('main_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'rand_product_img' => $randProductImg
                ];
            }
        }

        return $this->render('front/default/index.html.twig', [
            'categories' => $preparedListCategory
        ]);
    }
}
