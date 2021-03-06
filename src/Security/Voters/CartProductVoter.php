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

    /** @var RequestStack */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param string $attribute
     * @param mixed  $subject
     *
     * @return bool
     */
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

    /**
     * @param string         $attribute
     * @param mixed          $subject
     * @param TokenInterface $token
     *
     * @return bool
     */
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

    /**
     * @return bool
     */
    private function canRead(): bool
    {
        // ?????? ?????? ???????????????????????? ???????????????? ?? FilterCartQueryExtension.php
        return true;
    }

    /**
     * @param Cart $cart
     *
     * @return bool
     */
    private function canEdit(Cart $cart): bool
    {
        // ???????? ?????????????? ?????? ???? ????????????????????
        if (!$cart->getId()) {
            return true;
        }

        $cartToken = $this->getCartToken();

        if (!$cartToken) {
            return false;
        }

        // ??????????????????, ?????? ?????? ?????????????? ????????????????????????
        return $cart->getToken() === $cartToken;
    }

    /**
     * @param Cart $cart
     *
     * @return bool
     */
    private function canDelete(Cart $cart): bool
    {
        $cartToken = $this->getCartToken();

        if (!$cartToken || !$cart->getId()) {
            return false;
        }

        // ??????????????????, ?????? ?????? ?????????????? ????????????????????????
        return $cart->getToken() === $cartToken;
    }

    /**
     * @return string|null
     */
    private function getCartToken(): ?string
    {
        return $this->requestStack
            ->getCurrentRequest()
            ->cookies
            ->get('CART_TOKEN');
    }
}
