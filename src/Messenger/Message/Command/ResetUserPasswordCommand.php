<?php

declare(strict_types=1);

namespace App\Messenger\Message\Command;

class ResetUserPasswordCommand
{
    /**
     * @var string
     */
    private $email;

    /**
     * @param string $email
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }
}
