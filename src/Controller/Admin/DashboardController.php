<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin")
 */
class DashboardController extends AbstractController
{
    /**
     * @Route("/dashboard", name="admin_dashboard_show")
     *
     * @return Response
     */
    public function dashboard(): Response
    {
        return $this->render('admin/pages/dashboard.html.twig');
    }
}
