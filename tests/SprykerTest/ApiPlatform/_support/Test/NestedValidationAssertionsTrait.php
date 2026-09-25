<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use Symfony\Component\HttpFoundation\Response;

/**
 * Assertions for the validation rules that sit inside a nested object rather than on the resource's
 * own properties.
 *
 * Every assertion matches a response `detail` exactly rather than searching the body. A substring
 * check cannot tell a real leaf violation from the single object-level error the API answers when a
 * whole nested object fails to denormalize, and telling those two apart is the only reason to cover
 * the leaves individually.
 */
trait NestedValidationAssertionsTrait
{
    /**
     * Asserts that each path below the nested object answered exactly the expected message. Paths
     * are relative to `$objectPath`, and a leaf that trips several rules at once takes a list.
     *
     * @param array<string, string|array<string>> $expectedByPath
     */
    protected function assertNestedViolations(Response $response, string $objectPath, array $expectedByPath): void
    {
        $details = $this->violationDetails($response);

        foreach ($expectedByPath as $path => $messages) {
            foreach ((array)$messages as $message) {
                $this->assertContains(
                    sprintf('%s.%s => %s', $objectPath, $path, $message),
                    $details,
                    sprintf('Expected a violation for "%s". Got: %s', $path, implode(' | ', $details)),
                );
            }
        }
    }

    /**
     * A leaf whose submitted value cannot be assigned to the generated property is reported without
     * the nested object's path prefix, unlike every other leaf violation.
     */
    protected function assertUnprefixedNestedViolation(Response $response, string $path, string $message): void
    {
        $this->assertViolation($response, $path, $message);
    }

    /**
     * Asserts a violation reported against a path of its own — the object property itself rather
     * than a leaf below it, as a `NotBlank` guarding a nested object reports.
     */
    protected function assertViolation(Response $response, string $path, string $message): void
    {
        $details = $this->violationDetails($response);

        $this->assertContains(
            sprintf('%s => %s', $path, $message),
            $details,
            sprintf('Expected a violation for "%s". Got: %s', $path, implode(' | ', $details)),
        );
    }

    /**
     * @return array<string>
     */
    protected function violationDetails(Response $response): array
    {
        $body = (string)$response->getContent();
        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $body);

        $details = [];
        foreach (json_decode($body, true)[static::JSON_API_KEY_ERRORS] ?? [] as $error) {
            if (isset($error[static::JSON_API_KEY_DETAIL])) {
                $details[] = $error[static::JSON_API_KEY_DETAIL];
            }
        }

        return $details;
    }
}
