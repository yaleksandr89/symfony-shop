<?php

namespace App\Utils\Oauth2\Vk;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class VkUser implements ResourceOwnerInterface
{
    protected array $response;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

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
            $fullname .= $this->response['first_name'].' ';
        }

        if ($this->response['last_name']) {
            $fullname .= $this->response['last_name'];
        }

        return $fullname;
    }

    public function toArray(): array
    {
        return $this->response;
    }
}
