<?php

declare(strict_types=1);

namespace App\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiPlatform\Input\CheckoutOrderInput;
use App\Entity\Order;
use App\Entity\StaticStorage\OrderStaticStorage;
use App\Entity\User;
use App\Event\OrderCreatedFromCartEvent;
use App\Utils\Manager\OrderManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<CheckoutOrderInput, Order>
 */
final class CheckoutOrderProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<Order, Order> $persistProcessor
     */
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly OrderManager $orderManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ValidatorInterface $validator,
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        if (!$data instanceof CheckoutOrderInput || !is_int($data->cartId) || $data->cartId <= 0) {
            throw new BadRequestHttpException('Invalid checkout cart.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $cart = $this->orderManager->findCart($data->cartId);
        if (!$cart || $cart->getCartProducts()->isEmpty()) {
            throw new BadRequestHttpException('Checkout cart is unavailable.');
        }

        $request = $this->requestStack->getCurrentRequest();
        $cartToken = $request?->cookies->get('CART_TOKEN');
        if (!is_string($cartToken) || '' === $cartToken || $cart->getToken() !== $cartToken) {
            throw new BadRequestHttpException('Checkout cart is unavailable.');
        }

        foreach ($cart->getCartProducts() as $cartProduct) {
            if (0 < count($this->validator->validate($cartProduct))) {
                throw new BadRequestHttpException('Checkout cart is unavailable.');
            }
        }

        $order = (new Order())
            ->setOwner($user)
            ->setStatus(OrderStaticStorage::ORDER_STATUS_CREATED);
        $this->orderManager->addOrdersProductsFromVerifiedCart($order, $cart);
        $this->orderManager->calculationOrderTotalPrice($order);

        $persistedOrder = $this->persistProcessor->process($order, $operation, $uriVariables, $context);
        if (!$persistedOrder instanceof Order) {
            throw new \LogicException('The checkout order processor must persist an order.');
        }

        $this->eventDispatcher->dispatch(new OrderCreatedFromCartEvent($persistedOrder));

        return $persistedOrder;
    }
}
