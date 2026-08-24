<?php

declare(strict_types=1);

namespace App\OAuthBundle\Security\OAuth;

use App\Entity\User;
use App\OAuthBundle\Security\OAuth\Exception\OAuthLinkIntentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class OAuthLinkIntentStore
{
    public const TTL_SECONDS = 600;

    private const SESSION_KEY = 'oauth_link_intent';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
    ) {
    }

    public function store(User $user, OAuthProvider $provider, string $state): void
    {
        $userId = $user->getId();
        if (null === $userId || '' === trim($state)) {
            throw new OAuthLinkIntentException('Unable to create OAuth link intent.');
        }

        $intent = new OAuthLinkIntent($userId, $provider, hash('sha256', $state), $this->clock->now());
        $this->session()->set(self::SESSION_KEY, [
            'userId' => $intent->userId,
            'provider' => $intent->provider->value,
            'stateHash' => $intent->stateHash,
            'issuedAt' => $intent->issuedAt->getTimestamp(),
        ]);
    }

    public function hasPending(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request && $request->hasSession() && $request->getSession()->has(self::SESSION_KEY);
    }

    public function consume(User $user, OAuthProvider $provider, ?string $state): OAuthLinkIntent
    {
        $rawIntent = $this->session()->remove(self::SESSION_KEY);
        if (!is_array($rawIntent)
            || !isset($rawIntent['userId'], $rawIntent['provider'], $rawIntent['stateHash'], $rawIntent['issuedAt'])
            || !is_int($rawIntent['userId'])
            || !is_string($rawIntent['provider'])
            || !is_string($rawIntent['stateHash'])
            || !is_int($rawIntent['issuedAt'])
        ) {
            throw new OAuthLinkIntentException('Missing OAuth link intent.');
        }

        try {
            $intent = new OAuthLinkIntent(
                $rawIntent['userId'],
                OAuthProvider::from($rawIntent['provider']),
                $rawIntent['stateHash'],
                (new \DateTimeImmutable())->setTimestamp($rawIntent['issuedAt']),
            );
        } catch (\ValueError|\InvalidArgumentException) {
            throw new OAuthLinkIntentException('Malformed OAuth link intent.');
        }

        $userId = $user->getId();
        $state = null === $state ? '' : $state;
        if (null === $userId
            || $intent->userId !== $userId
            || $intent->provider !== $provider
            || '' === trim($state)
            || $this->clock->now()->getTimestamp() > $intent->issuedAt->getTimestamp() + self::TTL_SECONDS
            || !hash_equals($intent->stateHash, hash('sha256', $state))
        ) {
            throw new OAuthLinkIntentException('Invalid OAuth link intent.');
        }

        return $intent;
    }

    public function clear(): void
    {
        $this->session()->remove(self::SESSION_KEY);
    }

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            throw new OAuthLinkIntentException('OAuth link requires a session.');
        }

        return $request->getSession();
    }
}
