<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends BaseAdminController
{
    #[Route('/dashboard', name: 'admin_dashboard_show')]
    public function dashboard(): Response
    {
        return $this->render('admin/pages/dashboard.html.twig');
    }
}
