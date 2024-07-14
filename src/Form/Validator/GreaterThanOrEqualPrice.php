<?php

declare(strict_types=1);

namespace App\Form\Validator;

use Symfony\Component\Validator\Constraint;

class GreaterThanOrEqualPrice extends Constraint
{
    public string $message = 'Price cannot be less than or equal to zero.';

    public function getTargets(): array|string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
