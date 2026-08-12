<?php

namespace App\Tests\Functional\ApiPlatform;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\Response;

class ResourceTestUtils extends WebTestCase
{
    protected string $uriKey = '';

    protected const REQUEST_HEADERS = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/json',
    ];

    protected const REQUEST_HEADERS_PATCH = [
        'HTTP_ACCEPT' => 'application/ld+json',
        'CONTENT_TYPE' => 'application/merge-patch+json',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getResponseDecodedContent(AbstractBrowser $client): array
    {
        $content = $client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertJson($content);

        $decodedContent = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decodedContent);

        return $decodedContent;
    }

    protected function assertSecurityProblem(AbstractBrowser $client, int $status): void
    {
        self::assertContains($status, [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
        self::assertSame($status, $client->getResponse()->getStatusCode());

        $expected = Response::HTTP_UNAUTHORIZED === $status
            ? [
                'type' => 'about:blank',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication is required to access this resource.',
            ]
            : [
                'type' => 'about:blank',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'You do not have permission to access this resource.',
            ];

        self::assertSame($expected, $this->getResponseDecodedContent($client));

        $headers = $client->getResponse()->headers;
        self::assertSame('application/problem+json', $headers->get('content-type'));
        self::assertStringContainsString('no-store', (string) $headers->get('cache-control'));
        self::assertFalse($headers->has('location'));

        if (Response::HTTP_UNAUTHORIZED === $status) {
            self::assertSame(
                'ShopSession realm="symfony-shop", login-uri="/ru/login"',
                $headers->get('www-authenticate'),
            );
        } else {
            self::assertFalse($headers->has('www-authenticate'));
        }
    }
}
