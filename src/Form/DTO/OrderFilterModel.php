<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;
use DateTimeImmutable;

class OrderFilterModel
{
    public int|float|null $id = null;

    public ?User $owner = null;

    public ?int $status = null;

    /** @var array{left_number: int|float|null, right_number: int|float|null} */
    public array $totalPrice = [
        'left_number' => null,
        'right_number' => null,
    ];

    /** @var array{left_date: DateTimeImmutable|null, right_date: DateTimeImmutable|null} */
    public array $createdAt = [
        'left_date' => null,
        'right_date' => null,
    ];
}
