<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Callback(callback: 'validate')]
class EditUserModel
{
    public function __construct(
        public ?int $id = null,
        public ?string $plainPassword = null,
        public ?array $roles = null,
        public ?string $fullName = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?int $zipCode = null,
        public ?bool $isDeleted = null,
        public ?string $email = null
    ) {
    }

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
    }
}
