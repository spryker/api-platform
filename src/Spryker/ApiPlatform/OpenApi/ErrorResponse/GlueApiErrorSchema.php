<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

/**
 * JSON Schema of the error document every Glue API error response carries, as the runtime emits it rather than
 * as API Platform's own `Error` resource describes it. The members mirror the producers:
 * {@see \Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber} (`code`, `status`, `detail`, `message`),
 * {@see \Spryker\ApiPlatform\Serializer\TranslatingConstraintViolationListNormalizer} (`code`, `status`, `detail`)
 * and the legacy Glue error transfer, which carries `message` in place of `detail` and serializes an unset code as
 * `null`. `status` is therefore the only member present on every error.
 */
class GlueApiErrorSchema
{
    public const string SCHEMA_NAME = 'GlueApiError.jsonapi';

    public const string REFERENCE = '#/components/schemas/GlueApiError.jsonapi';

    protected const string TYPE_OBJECT = 'object';

    protected const string TYPE_ARRAY = 'array';

    protected const string TYPE_STRING = 'string';

    protected const string TYPE_INTEGER = 'integer';

    protected const string TYPE_NULL = 'null';

    protected const string PROPERTY_ERRORS = 'errors';

    protected const string PROPERTY_CODE = 'code';

    protected const string PROPERTY_STATUS = 'status';

    protected const string PROPERTY_DETAIL = 'detail';

    protected const string PROPERTY_MESSAGE = 'message';

    protected const int MINIMUM_ERRORS = 1;

    protected const string DESCRIPTION_DOCUMENT = 'Error document returned by every Glue API error response.';

    protected const string DESCRIPTION_CODE = 'Spryker error code, present when the endpoint defines one (for example `901` for a validation failure); `null` when a legacy Glue error carries none.';

    protected const string DESCRIPTION_STATUS = 'HTTP status code of the response.';

    protected const string DESCRIPTION_DETAIL = 'Human-readable explanation of the error. Validation errors prefix it with the attribute name: `email => This field is missing.`';

    protected const string DESCRIPTION_MESSAGE = 'Legacy duplicate of `detail`, kept for backwards compatibility with Glue REST; a few responses carry `message` instead of `detail`.';

    /**
     * Written in the OpenAPI 3.1 form; a document requested as 3.0.0 is downgraded by
     * {@see \ApiPlatform\OpenApi\Serializer\LegacyOpenApiNormalizer}, which spells the nullable `code` with the
     * `nullable` keyword the way it does for every other component schema.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'type' => static::TYPE_OBJECT,
            'description' => static::DESCRIPTION_DOCUMENT,
            'required' => [static::PROPERTY_ERRORS],
            'properties' => [
                static::PROPERTY_ERRORS => [
                    'type' => static::TYPE_ARRAY,
                    'minItems' => static::MINIMUM_ERRORS,
                    'items' => [
                        'type' => static::TYPE_OBJECT,
                        'required' => [static::PROPERTY_STATUS],
                        'properties' => [
                            static::PROPERTY_CODE => ['type' => [static::TYPE_STRING, static::TYPE_NULL], 'description' => static::DESCRIPTION_CODE],
                            static::PROPERTY_STATUS => ['type' => static::TYPE_INTEGER, 'description' => static::DESCRIPTION_STATUS],
                            static::PROPERTY_DETAIL => ['type' => static::TYPE_STRING, 'description' => static::DESCRIPTION_DETAIL],
                            static::PROPERTY_MESSAGE => ['type' => static::TYPE_STRING, 'description' => static::DESCRIPTION_MESSAGE],
                        ],
                    ],
                ],
            ],
        ];
    }
}
