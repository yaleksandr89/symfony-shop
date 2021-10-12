<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @Assert\Length(max="255")
     * @var string
     */
    public $fullName;

    /**
     * @Assert\Length(max="30")
     * @Assert\Regex(pattern="/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/")
     * @var string
     */
    public $phone;

    /**
     * @Assert\Length(max="255")
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
     * @Assert\Email
     * @Assert\Length(max="180")
     * @var string
     */
    public $email;

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
        $model->email = $user->getEmail();

        return $model;
    }
}