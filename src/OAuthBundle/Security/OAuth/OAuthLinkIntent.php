<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

final class OAuthLinkIntent
{
    public function __construct(
        public readonly int $userId,
        public readonly OAuthProvider $provider,
        public readonly string $stateHash,
        public readonly \DateTimeImmutable $issuedAt,
    ) {
        if ($userId < 1 || 64 !== strlen($stateHash) || !ctype_xdigit($stateHash)) {
            throw new \InvalidArgumentException('Invalid OAuth link intent.');
        }
    }
}
