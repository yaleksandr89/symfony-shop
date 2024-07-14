<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[
    Table(name: '`reset_password_request`'),
    Entity(repositoryClass: ResetPasswordRequestRepository::class)
]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    #[Id, GeneratedValue, Column(type: Types::INTEGER)]
    protected ?int $id;

    #[ManyToOne(targetEntity: User::class), JoinColumn(nullable: false)]
    protected object $user;

    public function __construct(object $user, DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->id = null;
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): object
    {
        return $this->user;
    }
}
