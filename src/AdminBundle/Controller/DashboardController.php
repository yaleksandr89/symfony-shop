<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class DashboardController extends BaseAdminController
{
    #[Route('/dashboard', name: 'admin_dashboard_show')]
    public function dashboard(): Response
    {
        return $this->render('@Admin/pages/dashboard.html.twig');
    }
}
