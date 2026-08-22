<?php

declare(strict_types=1);

namespace App\Commerce\Security\Voter;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\User;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CartProductVoter extends Voter
{
    private const CART_PRODUCT_READ = 'CART_PRODUCT_READ';
    private const CART_PRODUCT_EDIT = 'CART_PRODUCT_EDIT';
    private const CART_PRODUCT_DELETE = 'CART_PRODUCT_DELETE';

    public function __construct(private RequestStack $requestStack)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (!in_array($attribute, [self::CART_PRODUCT_READ, self::CART_PRODUCT_EDIT, self::CART_PRODUCT_DELETE])) {
            return false;
        }

        if (!$subject instanceof CartProduct) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->isAdminRole()) {
            return true;
        }

        if (!$user instanceof User) {
            $user = null;
        }

        /** @var CartProduct $cartProduct */
        $cartProduct = $subject;

        /** @var Cart $cart */
        $cart = $cartProduct->getCart();

        switch ($attribute) {
            case self::CART_PRODUCT_READ:
                return $this->canRead($cart);
            case self::CART_PRODUCT_EDIT:
                return $this->canEdit($cart);
            case self::CART_PRODUCT_DELETE:
                return $this->canDelete($cart);
        }

        throw new LogicException('This code should not be reached!');
    }

    private function canRead(Cart $cart): bool
    {
        $cartToken = $this->getCartToken();

        return null !== $cartToken && '' !== $cartToken && $cart->getToken() === $cartToken;
    }

    private function canEdit(Cart $cart): bool
    {
        // если корзина еще не существует
        if (!$cart->getId()) {
            return true;
        }

        $cartToken = $this->getCartToken();

        if (!$cartToken) {
            return false;
        }

        // проверяем, что это корзина пользователя
        return $cart->getToken() === $cartToken;
    }

    private function canDelete(Cart $cart): bool
    {
        $cartToken = $this->getCartToken();

        if (!$cartToken || !$cart->getId()) {
            return false;
        }

        // проверяем, что это корзина пользователя
        return $cart->getToken() === $cartToken;
    }

    private function getCartToken(): ?string
    {
        $cartToken = $this->requestStack
            ->getCurrentRequest()
            ?->cookies
            ->get('CART_TOKEN');

        return is_string($cartToken) ? $cartToken : null;
    }
}
