<?php

declare(strict_types=1);

namespace App\Security\Handler;

use App\Security\RequestMatcher\ApiRequestMatcher;
use App\Security\Response\ApiSecurityProblemResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

final class RequestAwareAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private ApiRequestMatcher $apiRequestMatcher,
        private ApiSecurityProblemResponder $apiSecurityProblemResponder,
        private AccessFrontDeniedHandler $accessFrontDeniedHandler,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($this->apiRequestMatcher->matches($request)) {
            return $this->apiSecurityProblemResponder->forbidden();
        }

        return $this->accessFrontDeniedHandler->handle($request, $accessDeniedException);
    }
}
