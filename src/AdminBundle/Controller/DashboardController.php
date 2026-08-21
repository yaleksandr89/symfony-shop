<?php

declare(strict_types=1);

namespace App\AdminBundle\Controller;

use Symfony\Component\HttpFoundation\Response;

class DashboardController extends BaseAdminController
{
    public function dashboard(): Response
    {
        return $this->render('@Admin/pages/dashboard.html.twig');
    }
}
