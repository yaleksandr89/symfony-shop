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
use App\Repository\CartRepository;
use App\Utils\Manager\OrderManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly OrderManager $orderManager,
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ValidatorInterface $validator,
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

        $request = $this->requestStack->getCurrentRequest();
        $cartToken = $request?->cookies->get('CART_TOKEN');

        $order = $this->entityManager->wrapInTransaction(function () use ($data, $user, $cartToken): Order {
            $cart = $this->cartRepository->findForCheckout($data->cartId);
            if (!$cart || $cart->getCartProducts()->isEmpty()) {
                throw new BadRequestHttpException('Checkout cart is unavailable.');
            }

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

            $this->entityManager->persist($order);
            $this->entityManager->remove($cart);

            return $order;
        });

        if (!$order instanceof Order) {
            throw new \LogicException('The checkout transaction must return an order.');
        }

        $this->eventDispatcher->dispatch(new OrderCreatedFromCartEvent($order));

        return $order;
    }
}
