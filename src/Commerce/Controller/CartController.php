<?php

declare(strict_types=1);

namespace App\Commerce\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class CartController extends AbstractController
{
    public function show(): Response
    {
        return $this->render('commerce/cart/show.html.twig');
    }
}
