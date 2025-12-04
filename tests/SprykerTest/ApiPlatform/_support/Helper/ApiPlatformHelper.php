<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use RuntimeException;
use SprykerTest\Shared\Testify\Helper\SymfonyHelper;
use Symfony\Component\HttpFoundation\Response;

/**
 * Codeception helper specialized for testing API Platform applications.
 *
 * Provides JSON assertions, resource testing, and API-specific utilities
 * adapted for Codeception without Doctrine.
 *
 * Based on API Platform's testing approach but adapted for:
 * - Codeception test framework (not PHPUnit)
 * - Spryker's architecture (no Doctrine dependency)
 * - RESTful API testing patterns
 * - Multiple API formats (JSON-LD, HAL, JSON:API)
 */
class ApiPlatformHelper extends Module
{
    protected ?Response $lastResponse = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'default_format' => 'json-ld',
    ];

    protected function getSymfonyHelper(): SymfonyHelper
    {
        /** @var \SprykerTest\Shared\Testify\Helper\SymfonyHelper */
        return $this->getModule(SymfonyHelper::class);
    }

    /**
     * @param array<string, mixed> $options Request options
     */
    public function request(
        string $method,
        string $uri,
        array $options = [],
    ): Response {
        $helper = $this->getSymfonyHelper();

        $server = array_merge(
            ['HTTP_ACCEPT' => $this->getAcceptHeader()],
            $options['server'] ?? [],
        );

        $parameters = $options['parameters'] ?? [];
        $files = $options['files'] ?? [];
        $content = $options['content'] ?? null;

        $helper->_request($method, $uri, $parameters, $files, $server, $content);

        $this->lastResponse = $helper->client->getResponse();

        return $this->lastResponse;
    }

    public function getLastResponse(): Response
    {
        if ($this->lastResponse === null) {
            throw new RuntimeException('No request has been made yet');
        }

        return $this->lastResponse;
    }

    /**
     * @param array<string, mixed> $expected
     */
    public function assertJsonEquals(array $expected): void
    {
        $actual = $this->getJsonResponse();

        Assert::assertEquals(
            $expected,
            $actual,
            'JSON response does not match expected structure',
        );
    }

    /**
     * Assert partial JSON match with nested support.
     *
     * @param array<string, mixed> $expected
     */
    public function assertJsonContains(array $expected): void
    {
        $actual = $this->getJsonResponse();

        $this->assertArrayContainsArray($expected, $actual);
    }

    public function assertJsonMatches(string $jsonPath, mixed $expectedValue): void
    {
        $actual = $this->getJsonResponse();
        $value = $this->evaluateJsonPath($jsonPath, $actual);

        Assert::assertEquals(
            $expectedValue,
            $value,
            sprintf('JSONPath "%s" does not match expected value', $jsonPath),
        );
    }

    public function assertResponseStatusCodeSame(int $expectedCode): void
    {
        $response = $this->getLastResponse();

        Assert::assertEquals(
            $expectedCode,
            $response->getStatusCode(),
            sprintf(
                'Expected status code %d, got %d. Response: %s',
                $expectedCode,
                $response->getStatusCode(),
                $response->getContent(),
            ),
        );
    }

    public function assertResponseHeaderSame(string $headerName, string $expectedValue): void
    {
        $response = $this->getLastResponse();
        $actualValue = $response->headers->get($headerName);

        Assert::assertEquals(
            $expectedValue,
            $actualValue,
            sprintf('Header "%s" does not match', $headerName),
        );
    }

    public function assertResponseFormatSame(string $format): void
    {
        $response = $this->getLastResponse();
        $contentType = $response->headers->get('Content-Type');

        $expectedContentType = $this->getContentTypeForFormat($format);

        Assert::assertStringContainsString(
            $expectedContentType,
            $contentType ?? '',
            sprintf('Expected format %s, got content type %s', $format, $contentType),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function assertResourceCollection(array $criteria): void
    {
        $data = $this->getJsonResponse();

        Assert::assertArrayHasKey(
            'hydra:member',
            $data,
            'Response is not a Hydra collection',
        );

        if (isset($criteria['totalItems'])) {
            Assert::assertEquals(
                $criteria['totalItems'],
                $data['hydra:totalItems'] ?? 0,
                'Total items count does not match',
            );
        }

        if (isset($criteria['memberCount'])) {
            Assert::assertCount(
                $criteria['memberCount'],
                $data['hydra:member'],
                'Member count does not match',
            );
        }
    }

    /**
     * @param array<string, mixed> $expectedData
     */
    public function assertResourceItem(array $expectedData): void
    {
        $actual = $this->getJsonResponse();

        $actualData = array_filter($actual, function ($key): bool {
            return !str_starts_with($key, '@') && !str_starts_with($key, 'hydra:');
        }, ARRAY_FILTER_USE_KEY);

        $this->assertArrayContainsArray($expectedData, $actualData);
    }

    public function assertHydraCollectionContains(int $totalItems): void
    {
        $data = $this->getJsonResponse();

        Assert::assertEquals(
            $totalItems,
            $data['hydra:totalItems'] ?? 0,
            'Hydra totalItems does not match',
        );
    }

    /**
     * @return string
     */
    public function extractIriFromResponse(): string
    {
        $data = $this->getJsonResponse();

        Assert::assertArrayHasKey(
            '@id',
            $data,
            'Response does not contain an IRI (@id)',
        );

        return $data['@id'];
    }

    /**
     * @return array<string>
     */
    public function extractIrisFromCollection(): array
    {
        $data = $this->getJsonResponse();

        Assert::assertArrayHasKey(
            'hydra:member',
            $data,
            'Response is not a Hydra collection',
        );

        return array_map(
            fn ($item): ?string => $item['@id'] ?? null,
            $data['hydra:member'],
        );
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @return array<string, string|null>
     */
    public function parseIri(string $iri): array
    {
        if (!preg_match('#^/api/([^/]+)/([^/]+)(?:/(.+))?$#', $iri, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid IRI format: %s', $iri),
            );
        }

        return [
            'apiType' => $matches[1],
            'resource' => $matches[2],
            'id' => $matches[3] ?? null,
        ];
    }

    /**
     * @throws \RuntimeException
     *
     * @return array<string, mixed>
     */
    protected function getJsonResponse(): array
    {
        $response = $this->getLastResponse();
        $content = $response->getContent();

        if ($content === false) {
            throw new RuntimeException('Unable to get response content');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('Invalid JSON response: %s', json_last_error_msg()),
            );
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    protected function assertArrayContainsArray(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            Assert::assertArrayHasKey(
                $key,
                $actual,
                sprintf('Key "%s" not found in response', $key),
            );

            if (is_array($value)) {
                $this->assertArrayContainsArray($value, $actual[$key]);

                continue;
            }

            Assert::assertEquals(
                $value,
                $actual[$key],
                sprintf('Value for key "%s" does not match', $key),
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws \RuntimeException
     */
    protected function evaluateJsonPath(string $jsonPath, array $data): mixed
    {
        $keys = explode('.', $jsonPath);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                throw new RuntimeException(
                    sprintf('JSONPath "%s" not found in response', $jsonPath),
                );
            }

            $current = $current[$key];
        }

        return $current;
    }

    protected function getAcceptHeader(): string
    {
        return match ($this->config['default_format']) {
            'json-ld' => 'application/ld+json',
            'hal' => 'application/hal+json',
            'json-api' => 'application/vnd.api+json',
            default => 'application/json',
        };
    }

    protected function getContentTypeForFormat(string $format): string
    {
        return match ($format) {
            'json-ld' => 'application/ld+json',
            'hal' => 'application/hal+json',
            'json-api' => 'application/vnd.api+json',
            default => 'application/json',
        };
    }
}
