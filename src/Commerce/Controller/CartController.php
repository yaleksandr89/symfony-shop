<?php

declare(strict_types=1);

namespace App\Commerce\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'main_cart_show')]
    public function show(): Response
    {
        return $this->render('commerce/cart/show.html.twig');
    }
}
