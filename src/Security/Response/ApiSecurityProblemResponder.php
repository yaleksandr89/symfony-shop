<?php

declare(strict_types=1);

namespace App\Security\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApiSecurityProblemResponder
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function unauthorized(Request $request): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication is required to access this resource.',
            ],
            Response::HTTP_UNAUTHORIZED,
            [
                'Content-Type' => 'application/problem+json',
                'Cache-Control' => 'no-store',
                'WWW-Authenticate' => sprintf(
                    'ShopSession realm="symfony-shop", login-uri="%s"',
                    $this->urlGenerator->generate('main_login')
                ),
            ],
        );
    }

    public function forbidden(): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to access this resource.',
            ],
            Response::HTTP_FORBIDDEN,
            [
                'Content-Type' => 'application/problem+json',
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
