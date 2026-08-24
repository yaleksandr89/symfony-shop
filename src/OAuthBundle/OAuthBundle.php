<?php

declare(strict_types=1);

namespace App\OAuthBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class OAuthBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}
