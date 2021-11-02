<?php

declare(strict_types=1);

namespace App\Utils\ApiPlatform\Event\Subscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Cart;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SetCartSessionIdSubscriber implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                'setSessionId', EventPriorities::PRE_WRITE
            ]
        ];
    }

    /**
     * @param ViewEvent $viewEvent
     */
    public function setSessionId(ViewEvent $viewEvent): void
    {
        $cart = $viewEvent->getControllerResult();
        $method = $viewEvent->getRequest()->getMethod();

        if (!$cart instanceof Cart || Request::METHOD_POST !== $method) {
            return;
        }

        $phpSessionId = $viewEvent->getRequest()->cookies->get('PHPSESSID');

        if (!$phpSessionId) {
            return;
        }

        $cart->setSessionId($phpSessionId);
    }
}