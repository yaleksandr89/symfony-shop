<?php

declare(strict_types=1);

namespace App\Security\OAuth;

enum OAuthProvider: string
{
    case Google = 'google';
    case Yandex = 'yandex';
    case Vkontakte = 'vkontakte';
    case GithubEn = 'github_en';
    case GithubRus = 'github_rus';
    case Facebook = 'facebook';
    case Linkedin = 'linkedin';
    case Mailru = 'mailru';

    public function isImplemented(): bool
    {
        return match ($this) {
            self::Google,
            self::Yandex,
            self::Vkontakte,
            self::GithubEn,
            self::GithubRus => true,
            self::Facebook,
            self::Linkedin,
            self::Mailru => false,
        };
    }

    public function identityFamily(): string
    {
        return match ($this) {
            self::Google => 'google',
            self::Yandex => 'yandex',
            self::Vkontakte => 'vkontakte',
            self::GithubEn,
            self::GithubRus => 'github',
            self::Facebook,
            self::Linkedin,
            self::Mailru => 'unsupported',
        };
    }

    public function isCurrentIdentityProvider(): bool
    {
        return 'unsupported' !== $this->identityFamily();
    }

    public function oauthClientName(): string
    {
        return match ($this) {
            self::Google => 'google_main',
            self::Yandex => 'yandex_main',
            self::Vkontakte => 'vkontakte_main',
            self::GithubEn => 'github_en',
            self::GithubRus => 'github_ru',
            default => throw new \LogicException('Unsupported OAuth client provider.'),
        };
    }

    public function callbackRoute(): string
    {
        return match ($this) {
            self::Google => 'connect_google_check',
            self::Yandex => 'connect_yandex_check',
            self::Vkontakte => 'connect_vkontakte_check',
            self::GithubEn => 'connect_github_en_check',
            self::GithubRus => 'connect_github_ru_check',
            default => throw new \LogicException('Unsupported OAuth callback provider.'),
        };
    }

    public function startRoute(): string
    {
        return match ($this) {
            self::Google => 'connect_google_start',
            self::Yandex => 'connect_yandex_start',
            self::Vkontakte => 'connect_vkontakte_start',
            self::GithubEn => 'connect_github_en_start',
            self::GithubRus => 'connect_github_ru_start',
            default => throw new \LogicException('Unsupported OAuth start provider.'),
        };
    }

    public static function fromRoute(string $route): ?self
    {
        return match ($route) {
            'connect_google_start', 'connect_google_check' => self::Google,
            'connect_yandex_start', 'connect_yandex_check' => self::Yandex,
            'connect_vkontakte_start', 'connect_vkontakte_check' => self::Vkontakte,
            'connect_github_en_start', 'connect_github_en_check' => self::GithubEn,
            'connect_github_ru_start', 'connect_github_ru_check' => self::GithubRus,
            default => null,
        };
    }
}
