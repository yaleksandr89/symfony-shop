<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{

    public function __construct(private string $defaultLocale)
    {
    }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->attributes->get('_locale');

        if ($locale) {
            $request->getSession()->set('_locale', $locale);
        } else {
            $request->setLocale(
                $request->getSession()->get('_locale', $this->defaultLocale)
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                [
                    'onKernelRequest',
                    20,
                ],
            ],
        ];
    }
}
