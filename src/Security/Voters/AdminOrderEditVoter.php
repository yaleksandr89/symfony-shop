<?php

declare(strict_types=1);

namespace App\Security\Voters;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AdminOrderEditVoter extends Voter
{
    private const CAN_ADMIN_EDIT = 'CAN_ADMIN_EDIT';

    /**
     * @param string $attribute
     * @param mixed  $subject
     *
     * @return bool
     */
    protected function supports(string $attribute, $subject): bool
    {
        // if the attribute isn't one we support, return false
        return self::CAN_ADMIN_EDIT === $attribute;
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
        /** @var User $user */
        $user = $token->getUser();
        $isVerified = $user->isVerified();
        $isAdmin = $user->isVerified();

        return $user instanceof User && $isAdmin && $isVerified;
    }
}
