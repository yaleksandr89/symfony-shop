<?php

declare(strict_types=1);

namespace App\Entity;

use App\Account\Repository\UserRepository;
use App\Account\User\UserStaticStorage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Table;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[Table(name: '`user`'),
    Entity(repositoryClass: UserRepository::class),
    UniqueEntity(fields: ['email'], message: 'У данной электронной почты уже зарегистрирована учетная запись')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    protected ?int $id;

    #[Column(type: Types::STRING, length: 180, unique: true)]
    protected ?string $email;

    #[Column(type: Types::JSON)]
    protected array $roles = [];

    #[Column(type: Types::STRING)]
    protected string $password;

    #[Column(type: Types::BOOLEAN)]
    protected bool $isVerified;

    #[Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $fullName;

    #[Column(type: Types::STRING, length: 30, nullable: true)]
    protected ?string $phone;

    #[Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $address;

    #[Column(type: Types::INTEGER, nullable: true)]
    protected ?int $zipCode;

    #[Column(type: Types::BOOLEAN)]
    protected bool $isDeleted;

    #[OneToMany(mappedBy: 'owner', targetEntity: Order::class)]
    protected Collection $orders;

    #[Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    protected ?string $googleId;

    #[Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    protected ?string $yandexId;

    #[Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    protected ?string $vkontakteId;

    #[Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    protected ?string $githubId;

    #[Column(type: Types::STRING, length: 50, unique: true, nullable: true)]
    protected ?string $facebookId = null;

    #[Column(type: Types::STRING, length: 255, unique: true, nullable: true)]
    protected ?string $linkedinId = null;

    public function __construct()
    {
        $this->id = null;
        $this->isVerified = false;
        $this->isDeleted = false;
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdminRole(): bool
    {
        return in_array('ROLE_ADMIN', $this->roles, true)
            || in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    public function hasAccessToAdminSection(): bool
    {
        foreach ($this->getRoles() as $role) {
            if (in_array($role, UserStaticStorage::getUserRoleHasAccessToAdminSection(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getPassword() !== $user->getPassword()) {
            return false;
        }

        $currentRoles = array_map('strval', $this->getRoles());
        $newRoles = array_map('strval', $user->getRoles());
        if (count($currentRoles) !== count($newRoles) || count($currentRoles) !== count(array_intersect($currentRoles, $newRoles))) {
            return false;
        }

        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }

        return $this->getIsDeleted() === $user->getIsDeleted();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(?string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getZipCode(): ?int
    {
        if (null === $this->zipCode) {
            return null;
        }

        return (int) $this->zipCode;
    }

    public function setZipCode(?int $zipCode): static
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(?bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): void
    {
        $this->googleId = $googleId;
    }

    public function getYandexId(): ?string
    {
        return $this->yandexId;
    }

    public function setYandexId(?string $yandexId): void
    {
        $this->yandexId = $yandexId;
    }

    public function getVkontakteId(): ?string
    {
        return $this->vkontakteId;
    }

    public function setVkontakteId(?string $vkontakteId): void
    {
        $this->vkontakteId = $vkontakteId;
    }

    public function getGithubId(): ?string
    {
        return $this->githubId;
    }

    public function setGithubId(?string $githubId): void
    {
        $this->githubId = $githubId;
    }

    public function getFacebookId(): ?string
    {
        return $this->facebookId;
    }

    public function setFacebookId(?string $facebookId): void
    {
        $this->facebookId = $facebookId;
    }

    public function getLinkedinId(): ?string
    {
        return $this->linkedinId;
    }

    public function setLinkedinId(?string $linkedinId): static
    {
        $this->linkedinId = $linkedinId;

        return $this;
    }
}
