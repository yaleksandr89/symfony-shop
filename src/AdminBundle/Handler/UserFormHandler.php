<?php

declare(strict_types=1);

namespace App\AdminBundle\Handler;

use App\Account\Repository\UserRepository;
use App\AdminBundle\DTO\EditUserModel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFormHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function processEditForm(EditUserModel $editUserModel): User
    {
        $user = new User();

        if ($editUserModel->id) {
            $user = $this->userRepository->find($editUserModel->id) ?? throw new NotFoundHttpException('User no longer exists.');
        }

        $this->entityManager->persist($user);
        $user = $this->fillingCategoryData($user, $editUserModel);
        $this->entityManager->flush();

        return $user;
    }

    private function fillingCategoryData(User $user, EditUserModel $editUserModel): User
    {
        $zipCode = (!is_int($editUserModel->zipCode))
            ? (int) $editUserModel->zipCode
            : $editUserModel->zipCode;

        $email = $editUserModel->email;

        if (!empty($editUserModel->plainPassword)) {
            $encodedPassword = $this->hasher->hashPassword($user, $editUserModel->plainPassword);
            $user->setPassword($encodedPassword);
        }

        if ($email) {
            $user->setEmail($email);
        }

        $user->setRoles($editUserModel->roles ?? throw new LogicException('Missing validated roles field.'));
        $user->setFullName($editUserModel->fullName);
        $user->setPhone($editUserModel->phone);
        $user->setAddress($editUserModel->address);
        $user->setZipCode($zipCode);
        $user->setIsDeleted($editUserModel->isDeleted ?? throw new LogicException('Missing validated isDeleted field.'));

        return $user;
    }
}
