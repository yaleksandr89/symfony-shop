<?php

declare(strict_types=1);

namespace App\Security\EntryPoint;

use App\Account\Security\EntryPoint\AuthenticationFrontEntryPoint;
use App\Security\RequestMatcher\ApiRequestMatcher;
use App\Security\Response\ApiSecurityProblemResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class RequestAwareAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ApiRequestMatcher $apiRequestMatcher,
        private ApiSecurityProblemResponder $apiSecurityProblemResponder,
        private AuthenticationFrontEntryPoint $authenticationFrontEntryPoint,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if ($this->apiRequestMatcher->matches($request)) {
            return $this->apiSecurityProblemResponder->unauthorized($request);
        }

        return $this->authenticationFrontEntryPoint->start($request, $authException);
    }
}
