<?php

declare(strict_types=1);

namespace App\Utils\Oauth2\Linkedin;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

final class LinkedinUser implements ResourceOwnerInterface
{
    private readonly string $id;

    public function __construct(private readonly array $response)
    {
        $id = $response['sub'] ?? null;
        if (!is_scalar($id) && !$id instanceof \Stringable) {
            throw new \UnexpectedValueException('LinkedIn resource owner response does not contain a valid subject.');
        }

        $this->id = trim((string) $id);
        if ('' === $this->id) {
            throw new \UnexpectedValueException('LinkedIn resource owner response does not contain a valid subject.');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return isset($this->response['name']) && is_scalar($this->response['name']) ? (string) $this->response['name'] : null;
    }

    public function getEmail(): ?string
    {
        return isset($this->response['email']) && is_scalar($this->response['email']) ? (string) $this->response['email'] : null;
    }

    public function toArray(): array
    {
        return $this->response;
    }
}
