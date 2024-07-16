<?php

declare(strict_types=1);

namespace App\Messenger\Message\Command;

class ResetUserPasswordCommand
{
    public function __construct(private string $email)
    {
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
