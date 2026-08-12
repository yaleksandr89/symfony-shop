<?php

declare(strict_types=1);

namespace App\Security\RequestMatcher;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

final class ApiRequestMatcher implements RequestMatcherInterface
{
    public function matches(Request $request): bool
    {
        return 1 === preg_match('#^/api(?:/|$)#D', $request->getPathInfo());
    }
}
