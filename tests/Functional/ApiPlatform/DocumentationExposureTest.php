<?php

declare(strict_types=1);

namespace App\Tests\Functional\ApiPlatform;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

#[Group(name: 'functional')]
final class DocumentationExposureTest extends WebTestCase
{
    /**
     * @var list<string>
     */
    private const DOCUMENTATION_URIS = [
        '/api/docs',
        '/api/docs.html',
        '/api/docs?ui=re_doc',
        '/api/docs.json',
        '/api/docs.jsonld',
        '/api/docs.jsonopenapi',
        '/api/docs.yamlopenapi',
        '/api/docs.foo',
    ];

    public function testDocumentationRemainsAvailableInTestEnvironment(): void
    {
        $client = self::createClient();

        foreach (['/api/docs', '/api/docs.html', '/api/docs?ui=re_doc'] as $uri) {
            $client->request('GET', $uri, [], [], ['HTTP_ACCEPT' => 'text/html']);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertStringContainsString('text/html', (string) $client->getResponse()->headers->get('content-type'));
            self::assertMatchesRegularExpression('/swagger-ui|redoc/i', (string) $client->getResponse()->getContent());
        }

        foreach ([
            '/api/docs.json' => 'application/json',
            '/api/docs.jsonopenapi' => 'application/vnd.openapi+json',
        ] as $uri => $accept) {
            $client->request('GET', $uri, [], [], ['HTTP_ACCEPT' => $accept]);

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            $document = $this->decodeResponse($client);
            self::assertTrue(isset($document['openapi']) || isset($document['swagger']));
            self::assertArrayHasKey('paths', $document);
        }

        $client->request('GET', '/api/docs.jsonld', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertArrayHasKey('@context', $this->decodeResponse($client));

        $client->request('GET', '/api/docs.yamlopenapi');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertMatchesRegularExpression('/\b(openapi|swagger):/', (string) $client->getResponse()->getContent());

        $client->request('GET', '/api/docs.foo');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDocumentationIsNotFoundOnlyInProduction(): void
    {
        self::ensureKernelShutdown();

        try {
            self::bootKernel(['environment' => 'prod', 'debug' => false]);
            $kernel = self::$kernel;
            self::assertNotNull($kernel);
            $router = $kernel->getContainer()->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);
            $routes = $router->getRouteCollection();

            foreach (['api_doc', 'api_entrypoint', 'api_jsonld_context', 'api_products_get_collection'] as $routeName) {
                self::assertNotNull($routes->get($routeName));
            }

            $client = new KernelBrowser($kernel);
            $client->disableReboot();

            foreach (self::DOCUMENTATION_URIS as $uri) {
                foreach (['GET', 'HEAD'] as $method) {
                    $client->request($method, $uri);

                    $response = $client->getResponse();
                    self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $method.' '.$uri);
                    self::assertFalse($response->headers->has('location'));
                    self::assertNotSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
                    self::assertNotSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
                    self::assertDoesNotMatchRegularExpression('/swagger ui|redoc|"openapi"|"paths"/i', (string) $response->getContent());
                }
            }

            $client->request('GET', '/api', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

            self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            $entrypoint = $this->decodeResponse($client);
            self::assertArrayHasKey('@context', $entrypoint);
            self::assertSame('Entrypoint', $entrypoint['@type']);

            $client->request('GET', '/api/contexts/Product', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

            self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            $context = $this->decodeResponse($client);
            self::assertIsArray($context['@context']);
            self::assertArrayHasKey('@vocab', $context['@context']);
            self::assertStringContainsString('/api/docs.jsonld#', (string) $context['@context']['@vocab']);
        } finally {
            self::ensureKernelShutdown();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertJson($content);

        $document = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        return $document;
    }
}
