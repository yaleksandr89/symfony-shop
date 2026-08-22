<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\Account\Repository\UserRepository;
use App\AdminBundle\DTO\EditUserModel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFormHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function processEditForm(EditUserModel $editUserModel): User
    {
        $user = new User();

        if ($editUserModel->id) {
            $user = $this->userRepository->find($editUserModel->id);
        }

        $this->entityManager->persist($user);
        $user = $this->fillingCategoryData($user, $editUserModel);
        $this->entityManager->flush();

        return $user;
    }

    private function fillingCategoryData(User $user, EditUserModel $editUserModel): User
    {
        $plainPassword = (!is_string($editUserModel->plainPassword))
            ? (string) $editUserModel->plainPassword
            : $editUserModel->plainPassword;

        $roles = (!is_array($editUserModel->roles))
            ? (array) $editUserModel->roles
            : $editUserModel->roles;

        $fullName = (!is_string($editUserModel->fullName))
            ? (string) $editUserModel->fullName
            : $editUserModel->fullName;

        $phone = (!is_string($editUserModel->phone))
            ? (string) $editUserModel->phone
            : $editUserModel->phone;

        $address = (!is_string($editUserModel->address))
            ? (string) $editUserModel->address
            : $editUserModel->address;

        $zipCode = (!is_int($editUserModel->zipCode))
            ? (int) $editUserModel->zipCode
            : $editUserModel->zipCode;

        $isDeleted = (!is_bool($editUserModel->isDeleted))
            ? (bool) $editUserModel->isDeleted
            : $editUserModel->isDeleted;

        $email = $editUserModel->email;

        if (!empty($plainPassword)) {
            $encodedPassword = $this->hasher->hashPassword($user, $plainPassword);
            $user->setPassword($encodedPassword);
        }

        if ($editUserModel->email) {
            $user->setEmail($email);
        }

        $user->setRoles($roles);
        $user->setFullName($fullName);
        $user->setPhone($phone);
        $user->setAddress($address);
        $user->setZipCode($zipCode);
        $user->setIsDeleted($isDeleted);

        return $user;
    }
}
