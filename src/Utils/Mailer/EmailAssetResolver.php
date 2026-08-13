<?php

declare(strict_types=1);

namespace App\Utils\Mailer;

use App\Utils\Mailer\Exception\EmailAssetUnavailableException;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EmailAssetResolver
{
    private const STYLESHEET = 'build/email.css';
    private const LOGO = 'icons/alexander-yurchenko-php-developer.png';

    public function __construct(
        private Packages $packages,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getStylesheet(): string
    {
        $path = $this->resolveStylesheetPath();
        $stylesheet = file_get_contents($path);

        if (false === $stylesheet) {
            throw new EmailAssetUnavailableException('Email stylesheet is unavailable.');
        }

        return $stylesheet;
    }

    public function getLogoPath(): string
    {
        return $this->resolveFileInDirectory(
            $this->projectDir.'/assets/images',
            self::LOGO,
            'email logo',
        );
    }

    private function resolveStylesheetPath(): string
    {
        $url = $this->packages->getUrl(self::STYLESHEET);

        if (str_starts_with($url, '//')) {
            throw new \RuntimeException(sprintf('Email stylesheet URL must be local: %s', $url));
        }

        $urlParts = parse_url($url);
        if (
            false === $urlParts
            || isset($urlParts['scheme'])
            || isset($urlParts['host'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])
            || isset($urlParts['port'])
        ) {
            throw new \RuntimeException(sprintf('Email stylesheet URL must be local: %s', $url));
        }

        $path = $urlParts['path'] ?? null;
        if (!is_string($path) || !str_starts_with($path, '/build/') || 'css' !== pathinfo($path, PATHINFO_EXTENSION)) {
            throw new \RuntimeException(sprintf('Email stylesheet URL must resolve inside /build: %s', $url));
        }

        return $this->resolveFileInDirectory(
            $this->projectDir.'/public/build',
            substr($path, strlen('/build/')),
            'email stylesheet',
        );
    }

    private function resolveFileInDirectory(string $directory, string $relativePath, string $asset): string
    {
        if ('' === $relativePath || str_contains($relativePath, "\0") || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $relativePath)) {
            throw new \RuntimeException(sprintf('Invalid %s path.', $asset));
        }

        $realDirectory = realpath($directory);
        if (false === $realDirectory || !is_dir($realDirectory)) {
            throw new EmailAssetUnavailableException(sprintf('%s directory is unavailable.', ucfirst($asset)));
        }

        $realPath = realpath($realDirectory.'/'.$relativePath);
        if (false === $realPath) {
            throw new EmailAssetUnavailableException(sprintf('%s is unavailable.', ucfirst($asset)));
        }

        if (!str_starts_with($realPath, $realDirectory.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf('Unable to resolve %s inside its expected directory.', $asset));
        }

        if (!is_file($realPath) || !is_readable($realPath)) {
            throw new EmailAssetUnavailableException(sprintf('%s is unavailable.', ucfirst($asset)));
        }

        return $realPath;
    }
}
