<?php

declare(strict_types=1);

namespace App\Commerce\ApiPlatform\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutOrderInput
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $cartId = null;
}
