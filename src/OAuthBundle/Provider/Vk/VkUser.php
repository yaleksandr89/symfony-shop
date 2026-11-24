<?php

declare(strict_types=1);

namespace App\OAuthBundle\Provider\Vk;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class VkUser implements ResourceOwnerInterface
{
    /** @param array<string, mixed> $response */
    public function __construct(protected array $response) {}

    public function getId(): string
    {
        return (string) $this->response['user_id'];
    }

    public function getEmail(): ?string
    {
        return $this->response['email'] ?? null;
    }

    public function getFullName(): ?string
    {
        $fullname = '';

        if ($this->response['first_name']) {
            $fullname .= $this->response['first_name'] . ' ';
        }

        if ($this->response['last_name']) {
            $fullname .= $this->response['last_name'];
        }

        return $fullname;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->response;
    }
}
