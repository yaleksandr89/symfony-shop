<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\User;
use DateTimeInterface;

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

    /** @var array{left_datetime: DateTimeInterface|null, right_datetime: DateTimeInterface|null} */
    public array $createdAt = [
        'left_datetime' => null,
        'right_datetime' => null,
    ];
}
