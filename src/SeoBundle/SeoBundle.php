<?php

declare(strict_types=1);

namespace App\SeoBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SeoBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
