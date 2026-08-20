<?php

declare(strict_types=1);

namespace App\AdminBundle\Validator;

use Symfony\Component\Validator\Constraint;

class GreaterThanOrEqualPrice extends Constraint
{
    public string $message = 'product.validation.price.greater_than_zero';

    public function getTargets(): array|string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
