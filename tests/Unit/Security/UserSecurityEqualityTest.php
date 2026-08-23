<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[Group(name: 'unit')]
final class UserSecurityEqualityTest extends TestCase
{
    #[TestDox('Одинаковое security-состояние считается равным независимо от порядка ролей')]
    public function testSameSecurityStateIsEqualRegardlessOfRoleOrder(): void
    {
        $currentUser = $this->user('user@example.test', 'password-hash', ['ROLE_ADMIN', 'ROLE_EDITOR']);
        $sessionUser = $this->user('user@example.test', 'password-hash', ['ROLE_EDITOR', 'ROLE_ADMIN']);

        self::assertTrue($currentUser->isEqualTo($sessionUser));
    }

    #[TestDox('Другой тип UserInterface не считается тем же локальным пользователем')]
    public function testForeignUserInterfaceImplementationIsNotEqual(): void
    {
        $currentUser = $this->user('user@example.test', 'password-hash', ['ROLE_USER']);
        $foreignUser = new InMemoryUser('user@example.test', 'password-hash', ['ROLE_USER']);

        self::assertFalse($currentUser->isEqualTo($foreignUser));
    }

    /** @param string[] $roles */
    #[DataProvider('changedSecurityStates')]
    #[TestDox('Изменение password, ролей или identifier нарушает security-равенство')]
    public function testSecurityStateChangeIsNotEqual(string $email, string $password, array $roles): void
    {
        $currentUser = $this->user('user@example.test', 'password-hash', ['ROLE_ADMIN']);
        $changedUser = $this->user($email, $password, $roles);

        self::assertFalse($currentUser->isEqualTo($changedUser));
    }

    /** @return iterable<string, array{string, string, string[]}> */
    public static function changedSecurityStates(): iterable
    {
        yield 'password changed' => ['user@example.test', 'changed-password-hash', ['ROLE_ADMIN']];
        yield 'roles changed' => ['user@example.test', 'password-hash', ['ROLE_SUPER_ADMIN']];
        yield 'identifier changed' => ['changed@example.test', 'password-hash', ['ROLE_ADMIN']];
    }

    /** @param string[] $roles */
    private function user(string $email, string $password, array $roles): User
    {
        return (new User())
            ->setEmail($email)
            ->setPassword($password)
            ->setRoles($roles);
    }
}
