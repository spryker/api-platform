<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Contract\Envelope;

use Spryker\ApiPlatform\Contract\Coverage\ApiOperation;
use Symfony\Component\HttpFoundation\Response;

/**
 * The JSON:API envelope every successful response owes its caller: the media type, a `data` member,
 * a `type` naming a resource the schema defines, a `self` link on every resource object the document
 * carries, included ones too, and a non-empty `id` on each of those whose resource declares an
 * identifier. A `GET` is held to the exact short name its operation serves; a write only to naming
 * some declared resource, because a write may answer another resource.
 *
 * The document-level `links.self` is not demanded here — assert it per response with
 * {@see \SprykerTest\ApiPlatform\Test\JsonApiResponseAssertionsTrait::assertJsonApiDocumentSelfLink()}.
 */
class JsonApiEnvelopeVerifier
{
    protected const string MEDIA_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string HEADER_CONTENT_TYPE = 'Content-Type';

    protected const string VERB_GET = 'GET';

    protected const int STATUS_SUCCESS_MIN = 200;

    protected const int STATUS_SUCCESS_MAX = 299;

    protected const int STATUS_NO_CONTENT = 204;

    protected const string KEY_DATA = 'data';

    protected const string KEY_INCLUDED = 'included';

    protected const string KEY_LINKS = 'links';

    protected const string KEY_SELF = 'self';

    protected const string KEY_TYPE = 'type';

    protected const string KEY_ID = 'id';

    /**
     * @param array<string> $schemaResourceShortNames Every short name the generated resources define.
     * @param array<string> $identifierDeclaringResourceShortNames The subset of those whose schema declares an identifier.
     *
     * @return array<string> One message per violation, empty when the envelope holds.
     */
    public function verify(
        ApiOperation $apiOperation,
        Response $response,
        string $expectedResourceShortName,
        array $schemaResourceShortNames,
        array $identifierDeclaringResourceShortNames
    ): array {
        $status = $response->getStatusCode();

        if ($status < static::STATUS_SUCCESS_MIN || $status > static::STATUS_SUCCESS_MAX) {
            return [];
        }

        $verb = strtoupper($apiOperation->verb);
        $operationKey = $apiOperation->dispatchKey();
        $mediaType = $response->headers->get(static::HEADER_CONTENT_TYPE);
        $body = (string)$response->getContent();

        if ($status === static::STATUS_NO_CONTENT) {
            return trim($body) === ''
                ? []
                : [sprintf('%s answered %d with a body; a no-content response carries none.', $operationKey, $status)];
        }

        $violations = $this->verifyMediaType($operationKey, $mediaType);

        $document = json_decode($body, true);
        if (!is_array($document)) {
            $violations[] = sprintf('%s answered %d with a body that is not a JSON document.', $operationKey, $status);

            return $violations;
        }

        return array_merge(
            $violations,
            $this->verifyData(
                $operationKey,
                $verb,
                $document,
                $expectedResourceShortName,
                $schemaResourceShortNames,
                $identifierDeclaringResourceShortNames,
            ),
            $this->verifyIncluded(
                $operationKey,
                $document,
                $schemaResourceShortNames,
                $identifierDeclaringResourceShortNames,
            ),
        );
    }

    /**
     * @return array<string>
     */
    protected function verifyMediaType(string $operationKey, ?string $mediaType): array
    {
        if ($mediaType !== null && str_starts_with($mediaType, static::MEDIA_TYPE_JSON_API)) {
            return [];
        }

        return [sprintf(
            '%s answered with media type "%s"; the JSON:API contract is "%s".',
            $operationKey,
            $mediaType ?? '(none)',
            static::MEDIA_TYPE_JSON_API,
        )];
    }

    /**
     * @param array<mixed> $document
     * @param array<string> $schemaResourceShortNames
     * @param array<string> $identifierDeclaringResourceShortNames
     *
     * @return array<string>
     */
    protected function verifyData(
        string $operationKey,
        string $verb,
        array $document,
        string $expectedResourceShortName,
        array $schemaResourceShortNames,
        array $identifierDeclaringResourceShortNames
    ): array {
        if (!array_key_exists(static::KEY_DATA, $document)) {
            return [sprintf('%s answered a document with no "data" member.', $operationKey)];
        }

        $data = $document[static::KEY_DATA];
        if (!is_array($data)) {
            return [sprintf('%s answered a "data" member that is neither a resource object nor a collection.', $operationKey)];
        }

        $violations = [];

        foreach ($this->resourceObjects($data) as $position => $resourceObject) {
            $path = 'data' . $position;

            $violations = array_merge(
                $violations,
                $this->verifySelfLink($operationKey, $path, $resourceObject),
                $this->verifyIdentifier($operationKey, $path, $resourceObject, $identifierDeclaringResourceShortNames),
                $verb === static::VERB_GET
                    ? $this->verifyDeclaredType($operationKey, $path, $resourceObject, $expectedResourceShortName)
                    : $this->verifyKnownType($operationKey, $path, $resourceObject, $schemaResourceShortNames),
            );
        }

        return $violations;
    }

    /**
     * @param array<mixed> $document
     * @param array<string> $schemaResourceShortNames
     * @param array<string> $identifierDeclaringResourceShortNames
     *
     * @return array<string>
     */
    protected function verifyIncluded(
        string $operationKey,
        array $document,
        array $schemaResourceShortNames,
        array $identifierDeclaringResourceShortNames
    ): array {
        $included = $document[static::KEY_INCLUDED] ?? null;
        if (!is_array($included)) {
            return [];
        }

        $violations = [];

        foreach ($included as $position => $resourceObject) {
            $path = sprintf('included[%s]', (string)$position);

            if (!is_array($resourceObject)) {
                $violations[] = sprintf('%s answered %s as something other than a resource object.', $operationKey, $path);

                continue;
            }

            $violations = array_merge(
                $violations,
                $this->verifySelfLink($operationKey, $path, $resourceObject),
                $this->verifyIdentifier($operationKey, $path, $resourceObject, $identifierDeclaringResourceShortNames),
                $this->verifyKnownType($operationKey, $path, $resourceObject, $schemaResourceShortNames),
            );
        }

        return $violations;
    }

    /**
     * @param array<mixed> $resourceObject
     *
     * @return array<string>
     */
    protected function verifyDeclaredType(
        string $operationKey,
        string $path,
        array $resourceObject,
        string $expectedResourceShortName
    ): array {
        $type = $this->typeOf($resourceObject);
        if ($type === null) {
            return [sprintf('%s answered %s with no "type".', $operationKey, $path)];
        }

        if ($type === $expectedResourceShortName) {
            return [];
        }

        return [sprintf(
            '%s answered %s.type "%s"; the operation serves the "%s" resource.',
            $operationKey,
            $path,
            $type,
            $expectedResourceShortName,
        )];
    }

    /**
     * @param array<mixed> $resourceObject
     * @param array<string> $schemaResourceShortNames
     *
     * @return array<string>
     */
    protected function verifyKnownType(
        string $operationKey,
        string $path,
        array $resourceObject,
        array $schemaResourceShortNames
    ): array {
        $type = $this->typeOf($resourceObject);
        if ($type === null) {
            return [sprintf('%s answered %s with no "type".', $operationKey, $path)];
        }

        if (in_array($type, $schemaResourceShortNames, true)) {
            return [];
        }

        return [sprintf('%s answered %s.type "%s", which no generated resource defines.', $operationKey, $path, $type)];
    }

    /**
     * @param array<mixed> $resourceObject
     *
     * @return array<string>
     */
    protected function verifySelfLink(string $operationKey, string $path, array $resourceObject): array
    {
        $links = $resourceObject[static::KEY_LINKS] ?? null;
        $self = is_array($links) ? ($links[static::KEY_SELF] ?? null) : null;

        if (is_string($self) && $self !== '') {
            return [];
        }

        return [sprintf('%s answered %s with no "links.self".', $operationKey, $path)];
    }

    /**
     * A resource object owes an identifier only when its own `type`'s resource declares one — an
     * `included` object belongs to another resource than the one the operation serves. A JSON
     * number is rejected with the rest: identifiers reach the wire as strings.
     *
     * @param array<mixed> $resourceObject
     * @param array<string> $identifierDeclaringResourceShortNames
     *
     * @return array<string>
     */
    protected function verifyIdentifier(
        string $operationKey,
        string $path,
        array $resourceObject,
        array $identifierDeclaringResourceShortNames
    ): array {
        $type = $this->typeOf($resourceObject);
        if ($type === null || !in_array($type, $identifierDeclaringResourceShortNames, true)) {
            return [];
        }

        $identifier = $resourceObject[static::KEY_ID] ?? null;
        if (is_string($identifier) && $identifier !== '') {
            return [];
        }

        return [sprintf(
            '%s answered %s with no "id"; the "%s" resource declares an identifier.',
            $operationKey,
            $path,
            $type,
        )];
    }

    /**
     * @param array<mixed> $resourceObject
     */
    protected function typeOf(array $resourceObject): ?string
    {
        $type = $resourceObject[static::KEY_TYPE] ?? null;

        return is_string($type) && $type !== '' ? $type : null;
    }

    /**
     * A `data` member is either one resource object or a list of them, keyed here by the path
     * suffix a violation message should show.
     *
     * @param array<mixed> $data
     *
     * @return array<string, array<mixed>>
     */
    protected function resourceObjects(array $data): array
    {
        if (!array_is_list($data)) {
            return ['' => $data];
        }

        $resourceObjects = [];

        foreach ($data as $position => $resourceObject) {
            if (is_array($resourceObject)) {
                $resourceObjects[sprintf('[%d]', $position)] = $resourceObject;
            }
        }

        return $resourceObjects;
    }
}
