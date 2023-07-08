<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Assert\Callback(callback="validate")
 */
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
     *
     * @var string
     */
    public $fullName;

    /**
     * @Assert\Length(max="30")
     * @Assert\Regex(pattern="/^((8|\+7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?[\d\- ]{7,10}$/")
     *
     * @var string
     */
    public $phone;

    /**
     * @Assert\Length(max="255")
     *
     * @var string
     */
    public $address;

    /**
     * @var int
     */
    public $zipCode;

    /**
     * @var bool
     */
    public $isDeleted;

    /**
     * @Assert\Email
     * @Assert\Length(max="180")
     *
     * @var string
     */
    public $email;

    public static function makeFromUser(?User $user): self
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

    public function validate(ExecutionContextInterface $context): void
    {
        /** @var Form $form */
        $form = $context->getRoot();

        /** @var UserRepository $userRepository */
        $userRepository = $form->getConfig()->getOption('user_repository');

        // Валидация email
        if ($userRepository->findOneBy(['email' => $this->email])) {
            $context->buildViolation('This email is already registered')
                ->atPath('email')
                ->addViolation();
        }

        // Валидация пароля
        if (!$this->plainPassword) {
            $context->buildViolation('The password can\'t be empty')
                ->atPath('plainPassword')
                ->addViolation();
        }
    }
}
