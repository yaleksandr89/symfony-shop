<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\StaticStorage\UserStaticStorage;
use App\Repository\UserRepository;
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
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[
    Table(name: '`user`'),
    Entity(repositoryClass: UserRepository::class),
    UniqueEntity(fields: ['email'], message: 'У данной электронной почты уже зарегистрирована учетная запись')
]
class User implements UserInterface, PasswordAuthenticatedUserInterface
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

    #[Column(type: Types::STRING, length: 50, nullable: true)]
    protected ?string $googleId;

    #[Column(type: Types::STRING, length: 50, nullable: true)]
    protected ?string $yandexId;

    #[Column(type: Types::STRING, length: 50, nullable: true)]
    protected ?string $vkontakteId;

    #[Column(type: Types::STRING, length: 50, nullable: true)]
    protected ?string $githubId;

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
     * @deprecated since Symfony 5.3, use getUserIdentifier instead
     */
    public function getUsername(): string
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
        $isAdmin = false;

        foreach ($this->roles as $role) {
            if ($isAdmin) {
                continue;
            }

            $isAdmin = in_array($role, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']);
        }

        return $isAdmin;
    }

    public function hasAccessToAdminSection(): bool
    {
        $hasAccess = false;

        foreach ($this->getRoles() as $role) {
            if ($hasAccess) {
                continue;
            }

            $hasAccess = in_array($role, UserStaticStorage::getUserRoleHasAccessToAdminSection(), true);
        }

        return $hasAccess;
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
     * Returning a salt is only needed, if you are not using a modern
     * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
     *
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function addOrder(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders[] = $order;
            $order->setOwner($this);
        }

        return $this;
    }

    public function removeOrder(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getOwner() === $this) {
                $order->setOwner(null);
            }
        }

        return $this;
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
}
