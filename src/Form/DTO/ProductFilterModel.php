<?php

declare(strict_types=1);

namespace App\Form\DTO;

use App\Entity\Category;
use DateTimeInterface;

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

    /** @var array{left_datetime: DateTimeInterface|null, right_datetime: DateTimeInterface|null} */
    public array $createdAt = [
        'left_datetime' => null,
        'right_datetime' => null,
    ];

    public ?string $isPublished = null;
}
