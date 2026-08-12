<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\OAuth\OAuthProvider;
use App\Security\OAuth\OAuthProviderAvailability;
use App\Security\OAuth\OAuthProviderConfigurationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class OAuthProviderAvailabilityRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly OAuthProviderAvailability $availability)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!is_string($route) || null === ($provider = OAuthProvider::fromRoute($route))) {
            return;
        }

        try {
            $this->availability->assertOperational($provider);
        } catch (OAuthProviderConfigurationException $exception) {
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getMessage(), $exception);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }
}
