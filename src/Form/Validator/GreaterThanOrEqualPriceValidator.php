<?php

declare(strict_types=1);

namespace App\Form\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class GreaterThanOrEqualPriceValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof GreaterThanOrEqualPrice) {
            throw new UnexpectedTypeException($constraint, GreaterThanOrEqualPrice::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Convert value to float if it's a string representation of a number
        if (is_string($value)) {
            $value = (float) str_replace(',', '.', $value); // Handle ',' as decimal separator
        }

        if (!is_numeric($value)) {
            throw new UnexpectedValueException($value, 'numeric');
        }

        if ($value <= 0) {
            $this->context->buildViolation($constraint->message)
                ->addViolation();
        }
    }
}
