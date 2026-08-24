<?php

declare(strict_types=1);

namespace App\AdminBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AdminBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
