<?php

declare(strict_types=1);

namespace App\Security\Voters;

use App\Entity\Cart;
use App\Entity\CartProduct;
use App\Entity\User;
use LogicException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
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
        // if the attribute isn't one we support, return false
        if (!in_array($attribute, [self::CART_PRODUCT_READ, self::CART_PRODUCT_EDIT, self::CART_PRODUCT_DELETE])) {
            return false;
        }

        // only vote on `CartProduct` objects
        if (!$subject instanceof CartProduct) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->isAdminRole()) {
            return true;
        }

        if (!$user instanceof User) {
            $user = null;
        }

        // you know $subject is a Post object, thanks to `supports()`
        /** @var CartProduct $cartProduct */
        $cartProduct = $subject;

        /** @var Cart $cart */
        $cart = $cartProduct->getCart();

        switch ($attribute) {
            case self::CART_PRODUCT_READ:
                return $this->canRead();
            case self::CART_PRODUCT_EDIT:
                return $this->canEdit($cart);
            case self::CART_PRODUCT_DELETE:
                return $this->canDelete($cart);
        }

        throw new LogicException('This code should not be reached!');
    }

    private function canRead(): bool
    {
        // так как отрабатывает проверка в FilterCartQueryExtension.php
        return true;
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
        return $this->requestStack
            ->getCurrentRequest()
            ->cookies
            ->get('CART_TOKEN');
    }
}
