<?php

declare(strict_types=1);

namespace App\Security\EventListener;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Symfony\EventListener\AddFormatListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

final class ApiSecurityContentNegotiationListener
{
    private const DEFERRED_EXCEPTION = '_api_security_deferred_not_acceptable';
    private const DEFERRED_PHASE = '_api_security_deferred_not_acceptable_phase';
    private const PHASE_POST_READ = 'post_read';
    private const PHASE_POST_DESERIALIZE = 'post_deserialize';

    public function __construct(private AddFormatListener $addFormatListener)
    {
    }

    public function onEarlyKernelRequest(RequestEvent $event): void
    {
        try {
            $this->addFormatListener->onKernelRequest($event);
        } catch (NotAcceptableHttpException $exception) {
            $request = $event->getRequest();
            $operation = $request->attributes->get('_api_operation');

            if (!$operation instanceof HttpOperation) {
                throw $exception;
            }

            $phase = null !== $operation->getSecurityPostDenormalize()
                ? self::PHASE_POST_DESERIALIZE
                : self::PHASE_POST_READ;

            if (null === $operation->getSecurity() && self::PHASE_POST_READ === $phase) {
                throw $exception;
            }

            $request->attributes->set(self::DEFERRED_EXCEPTION, $exception);
            $request->attributes->set(self::DEFERRED_PHASE, $phase);
            $this->initializeFormatWithoutAccept($event, $request);
        }
    }

    public function onPostReadKernelRequest(RequestEvent $event): void
    {
        $this->throwDeferredException($event->getRequest(), self::PHASE_POST_READ);
    }

    public function onPostDeserializeKernelRequest(RequestEvent $event): void
    {
        $this->throwDeferredException($event->getRequest(), self::PHASE_POST_DESERIALIZE);
    }

    private function initializeFormatWithoutAccept(RequestEvent $event, Request $request): void
    {
        $accept = $request->headers->all('accept');
        $request->headers->remove('Accept');

        try {
            $this->addFormatListener->onKernelRequest($event);
        } finally {
            $request->headers->set('Accept', $accept);
        }
    }

    private function throwDeferredException(Request $request, string $phase): void
    {
        if ($phase !== $request->attributes->get(self::DEFERRED_PHASE)) {
            return;
        }

        $exception = $request->attributes->get(self::DEFERRED_EXCEPTION);
        $request->attributes->remove(self::DEFERRED_EXCEPTION);
        $request->attributes->remove(self::DEFERRED_PHASE);

        if ($exception instanceof NotAcceptableHttpException) {
            throw $exception;
        }
    }
}
