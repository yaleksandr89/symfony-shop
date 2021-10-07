<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;

class EditUserModel
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $plainPassword;

    /**
     * @var array
     */
    public $roles;

    /**
     * @var string
     */
    public $fullName;

    /**
     * @var string
     */
    public $phone;

    /**
     * @var string
     */
    public $address;

    /**
     * @var string
     */
    public $zipCode;

    /**
     * @var bool
     */
    public $isDeleted;

    /**
     * @param User|null $user
     * @return static
     */
    public static function makeFromUser(?User $user): static
    {
        $model = new self();

        if (!$user) {
            return $model;
        }

        $model->id = $user->getId();
        $model->plainPassword = '';
        $model->roles = $user->getRoles();
        $model->fullName = $user->getFullName();
        $model->phone = $user->getPhone();
        $model->address = $user->getAddress();
        $model->zipCode = $user->getZipCode();
        $model->isDeleted = $user->getIsDeleted();

        return $model;
    }
}