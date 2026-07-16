<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Category;
use DateTimeImmutable;

class ProductFilterModel
{
    public int|float|null $id = null;

    public ?Category $category = null;

    public ?string $title = null;

    /** @var array{left_number: int|float|null, right_number: int|float|null} */
    public array $price = [
        'left_number' => null,
        'right_number' => null,
    ];

    /** @var array{left_number: int|float|null, right_number: int|float|null} */
    public array $quantity = [
        'left_number' => null,
        'right_number' => null,
    ];

    /** @var array{left_date: DateTimeImmutable|null, right_date: DateTimeImmutable|null} */
    public array $createdAt = [
        'left_date' => null,
        'right_date' => null,
    ];

    public ?string $isPublished = null;
}
