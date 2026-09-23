<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\OpenApi\ErrorResponse;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\OpenApi\Model\Example;
use ArrayObject;
use Spryker\ApiPlatform\Configuration\ApiPlatformConfig;
use Spryker\ApiPlatform\EventSubscriber\GlueApiExceptionSubscriber;
use Spryker\ApiPlatform\EventSubscriber\JsonApiRequestValidatorSubscriber;
use Spryker\ApiPlatform\Security\GlueAuthenticationEntryPoint;
use Spryker\ApiPlatform\Security\OauthAuthenticator;
use Spryker\ApiPlatform\Serializer\TranslatingConstraintViolationListNormalizer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the description and the examples of an error response from what the runtime really answers for that
 * status on the current application: the Backend API rejects a missing bearer with 401, a denied security
 * expression with 403 and the code 802 and a resource whose ACL mapping denies the user with the ACL validator's
 * text; the Storefront API answers a missing bearer with 403 and the code 002; a body that is empty, not a JSON:API
 * document or of the wrong resource type answers 400 with the request subscribers' texts; a `filter` query parameter
 * outside the `filter[resource.property]` form answers 400 with the Glue convention's code 011 on every operation;
 * and a resource's `securityCode` and `securityMessage` feed the 403 examples exactly as they feed the responses.
 * The Storefront API's 404 example comes from the not-found error an operation declares or its provider carries;
 * the Backend API documents every 404 with the one resource-not-found code and message of the legacy Glue
 * application instead of resource-specific ones.
 *
 * The `default` response covers what no status of its own documents: 405 for a method the path does not support,
 * 406 for an Accept header outside the configured formats and, on an operation with a body, 415 for a Content-Type
 * outside its input formats, all rendered by the framework with its own texts.
 *
 * A public operation (no security expression, no bearer requirement) cannot fail on the bearer, so its declared 401
 * and 403 get no bearer example: what it answers there — a credential failure on a token endpoint — is
 * module-specific, and a wrong example is worse than none.
 *
 * The KernelFeature and Glue convention texts are repeated here as literals: neither module is a dependency of this
 * one, and KernelFeature depends on it, so their constants cannot be referenced.
 */
class ErrorResponseBuilder
{
    public const string EXAMPLE_MISSING_ACCESS_TOKEN = 'missingAccessToken';

    public const string EXAMPLE_INVALID_ACCESS_TOKEN = 'invalidAccessToken';

    public const string EXAMPLE_UNAUTHENTICATED = 'unauthenticated';

    public const string EXAMPLE_FORBIDDEN = 'forbidden';

    public const string EXAMPLE_ACCESS_DENIED_BY_ACL = 'accessDeniedByAcl';

    public const string EXAMPLE_NOT_FOUND = 'notFound';

    public const string EXAMPLE_VALIDATION_FAILED = 'validationFailed';

    public const string EXAMPLE_INVALID_POST_DATA = 'invalidPostData';

    public const string EXAMPLE_INVALID_TYPE = 'invalidType';

    public const string EXAMPLE_POST_DATA_MISSING = 'postDataMissing';

    public const string EXAMPLE_UNSUPPORTED_FILTER_FORMAT = 'unsupportedFilterFormat';

    public const string EXAMPLE_METHOD_NOT_ALLOWED = 'methodNotAllowed';

    public const string EXAMPLE_NOT_ACCEPTABLE = 'notAcceptable';

    public const string EXAMPLE_UNSUPPORTED_MEDIA_TYPE = 'unsupportedMediaType';

    public const string EXAMPLE_ERROR = 'error';

    protected const string KEY_ERRORS = 'errors';

    protected const string KEY_CODE = 'code';

    protected const string KEY_STATUS = 'status';

    protected const string KEY_DETAIL = 'detail';

    protected const string KEY_MESSAGE = 'message';

    protected const string EXTRA_PROPERTY_SECURITY_CODE = 'securityCode';

    protected const string EXTRA_PROPERTY_SECURITY_MESSAGE = 'securityMessage';

    protected const string EXTRA_PROPERTY_SECURITY_BEARER_AUTH_REQUIRED = 'securityBearerAuthRequired';

    /**
     * @see \Spryker\Glue\KernelFeature\Security\Validator\BearerTokenValidator
     */
    protected const string DETAIL_AUTHORIZATION_HEADER_REQUIRED = 'Authorization header is required';

    /**
     * @see \Spryker\Glue\KernelFeature\Security\Validator\AclValidator
     */
    protected const string DETAIL_ACCESS_DENIED_BY_ACL = 'Access denied by ACL rules';

    /**
     * @see \Spryker\Glue\GlueJsonApiConvention\GlueJsonApiConventionConfig::ERROR_CODE_UNSUPPORTED_FILTER_FORMAT
     */
    protected const string ERROR_CODE_UNSUPPORTED_FILTER_FORMAT = '011';

    /**
     * @see \Spryker\Glue\GlueJsonApiConvention\GlueJsonApiConventionConfig::ERROR_MESSAGE_UNSUPPORTED_FILTER_FORMAT
     */
    protected const string MESSAGE_UNSUPPORTED_FILTER_FORMAT = 'Unsupported `Filter` format is used. Please use `filter[resource.property]`';

    /**
     * @see \Spryker\Glue\GlueApplication\GlueApplicationConfig::ERROR_CODE_RESOURCE_NOT_FOUND
     */
    protected const string ERROR_CODE_RESOURCE_NOT_FOUND_BACKEND = '007';

    /**
     * @see \Spryker\Glue\GlueApplication\GlueApplicationConfig::ERROR_MESSAGE_RESOURCE_NOT_FOUND
     */
    protected const string MESSAGE_RESOURCE_NOT_FOUND_BACKEND = 'Not found';

    /**
     * @see \ApiPlatform\Metadata\Util\ContentNegotiationTrait::getNotAcceptableHttpException()
     */
    protected const string DETAIL_NOT_ACCEPTABLE_FORMAT = 'Requested format "%s" is not supported. Supported MIME types are "%s".';

    /**
     * @see \ApiPlatform\State\Provider\ContentNegotiationProvider::getInputFormat()
     */
    protected const string DETAIL_UNSUPPORTED_MEDIA_TYPE_FORMAT = 'The content-type "%s" is not supported. Supported MIME types are "%s".';

    protected const string MIME_TYPE_REQUESTED_IN_EXAMPLE = 'text/plain';

    protected const string MIME_TYPE_LIST_SEPARATOR = '", "';

    /**
     * @var array<string>
     */
    protected const array HTTP_METHODS_WITH_BODY = ['POST', 'PATCH', 'PUT'];

    protected const string DETAIL_FIELD_MISSING_FORMAT = '%s => This field is missing.';

    protected const string DETAIL_VALUE_NOT_VALID = 'This value is not valid.';

    protected const string DETAIL_FALLBACK = 'Error';

    protected const string TRAILING_PERIOD = '.';

    protected const string SUMMARY_MISSING_ACCESS_TOKEN = 'Missing access token';

    protected const string SUMMARY_INVALID_ACCESS_TOKEN = 'Invalid or expired access token';

    protected const string SUMMARY_UNAUTHENTICATED = 'Not authenticated';

    protected const string SUMMARY_FORBIDDEN = 'Access denied';

    protected const string SUMMARY_NOT_FOUND = 'Resource not found';

    protected const string SUMMARY_VALIDATION_FAILED = 'Validation failed';

    protected const string SUMMARY_INVALID_POST_DATA = 'Body is not a JSON:API document';

    protected const string SUMMARY_INVALID_TYPE = 'Wrong resource type';

    protected const string SUMMARY_POST_DATA_MISSING = 'Empty body';

    protected const string SUMMARY_UNSUPPORTED_FILTER_FORMAT = 'Malformed filter parameter';

    protected const string SUMMARY_METHOD_NOT_ALLOWED = 'Method not supported on this path';

    protected const string SUMMARY_NOT_ACCEPTABLE = 'Accept header outside the supported formats';

    protected const string SUMMARY_UNSUPPORTED_MEDIA_TYPE = 'Content-Type outside the supported input formats';

    protected const string DESCRIPTION_BAD_REQUEST = 'Malformed query parameter, for example a `filter` not in `filter[resource.property]` form.';

    protected const string DESCRIPTION_BAD_REQUEST_BODY = 'Malformed request: a body that is empty or not a JSON:API document, a wrong resource type, or a `filter` query parameter not in `filter[resource.property]` form.';

    protected const string DESCRIPTION_UNAUTHORIZED_BACKEND = 'Missing, invalid or expired access token.';

    protected const string DESCRIPTION_UNAUTHORIZED_STOREFRONT = 'Invalid or expired access token.';

    protected const string DESCRIPTION_FORBIDDEN_BACKEND = 'The user has no ACL access to this resource.';

    protected const string DESCRIPTION_FORBIDDEN_STOREFRONT = 'Missing access token, or the authenticated user is not allowed to access this resource.';

    protected const string DESCRIPTION_NOT_FOUND = 'Resource not found.';

    protected const string DESCRIPTION_UNPROCESSABLE_ENTITY = 'Validation failed: one error per rejected attribute, each with code 901 and a `detail` prefixed with the attribute name.';

    protected const string DESCRIPTION_DEFAULT = 'Any other error: 405 for a method this path does not support, 406 for an Accept header outside the supported formats, 500 for an unexpected failure. 405 and 406 use this error document; a 500 carries API Platform\'s error resource, whose members `id`, `title` and `type` come in addition.';

    protected const string DESCRIPTION_DEFAULT_BODY = 'Any other error: 405 for a method this path does not support, 406 for an Accept header outside the supported formats, 415 for a request body in a Content-Type outside the supported input formats, 500 for an unexpected failure. 405, 406 and 415 use this error document; a 500 carries API Platform\'s error resource, whose members `id`, `title` and `type` come in addition.';

    protected const string DESCRIPTION_FORMAT = '%s.';

    /**
     * @var array<string>
     */
    protected array $supportedMimeTypes;

    /**
     * @param array<string, array<string>> $formats
     */
    public function __construct(
        protected readonly ApiPlatformConfig $apiPlatformConfig,
        protected readonly ProviderNotFoundErrorResolver $providerNotFoundErrorResolver,
        array $formats,
    ) {
        $this->supportedMimeTypes = array_values(array_unique(array_merge(...array_values($formats))));
    }

    public function buildDescription(int $status, ?HttpOperation $httpOperation = null): string
    {
        return match (true) {
            $status === Response::HTTP_BAD_REQUEST && $this->isBodyOperation($httpOperation) => static::DESCRIPTION_BAD_REQUEST_BODY,
            $status === Response::HTTP_BAD_REQUEST => static::DESCRIPTION_BAD_REQUEST,
            $status === Response::HTTP_UNAUTHORIZED && $this->isBackendApi() => static::DESCRIPTION_UNAUTHORIZED_BACKEND,
            $status === Response::HTTP_UNAUTHORIZED => static::DESCRIPTION_UNAUTHORIZED_STOREFRONT,
            $status === Response::HTTP_FORBIDDEN && $this->isBackendApi() => static::DESCRIPTION_FORBIDDEN_BACKEND,
            $status === Response::HTTP_FORBIDDEN => static::DESCRIPTION_FORBIDDEN_STOREFRONT,
            $status === Response::HTTP_NOT_FOUND => static::DESCRIPTION_NOT_FOUND,
            $status === Response::HTTP_UNPROCESSABLE_ENTITY => static::DESCRIPTION_UNPROCESSABLE_ENTITY,
            default => sprintf(static::DESCRIPTION_FORMAT, $this->getReasonPhrase($status)),
        };
    }

    public function buildDefaultDescription(?HttpOperation $httpOperation = null): string
    {
        return $this->answersUnsupportedMediaType($httpOperation) ? static::DESCRIPTION_DEFAULT_BODY : static::DESCRIPTION_DEFAULT;
    }

    /**
     * @param array<string> $requiredAttributes
     *
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\Example>
     */
    public function buildExamples(int $status, ?HttpOperation $httpOperation = null, array $requiredAttributes = []): ArrayObject
    {
        $examples = match ($status) {
            Response::HTTP_BAD_REQUEST => $this->buildBadRequestExamples($httpOperation),
            Response::HTTP_UNAUTHORIZED => $this->buildUnauthorizedExamples($httpOperation),
            Response::HTTP_FORBIDDEN => $this->buildForbiddenExamples($httpOperation),
            Response::HTTP_NOT_FOUND => $this->buildNotFoundExamples($httpOperation),
            Response::HTTP_UNPROCESSABLE_ENTITY => $this->buildValidationExamples($requiredAttributes),
            default => $this->buildGenericExamples($status),
        };

        return new ArrayObject($examples);
    }

    /**
     * @return \ArrayObject<string, \ApiPlatform\OpenApi\Model\Example>
     */
    public function buildDefaultExamples(?HttpOperation $httpOperation = null): ArrayObject
    {
        $examples = [
            static::EXAMPLE_METHOD_NOT_ALLOWED => $this->createExample(static::SUMMARY_METHOD_NOT_ALLOWED, [
                static::KEY_STATUS => Response::HTTP_METHOD_NOT_ALLOWED,
                static::KEY_DETAIL => $this->getReasonPhrase(Response::HTTP_METHOD_NOT_ALLOWED),
            ]),
            static::EXAMPLE_NOT_ACCEPTABLE => $this->createExample(static::SUMMARY_NOT_ACCEPTABLE, [
                static::KEY_STATUS => Response::HTTP_NOT_ACCEPTABLE,
                static::KEY_DETAIL => sprintf(
                    static::DETAIL_NOT_ACCEPTABLE_FORMAT,
                    static::MIME_TYPE_REQUESTED_IN_EXAMPLE,
                    implode(static::MIME_TYPE_LIST_SEPARATOR, $this->supportedMimeTypes),
                ),
            ]),
        ];

        if ($this->answersUnsupportedMediaType($httpOperation)) {
            $examples[static::EXAMPLE_UNSUPPORTED_MEDIA_TYPE] = $this->createExample(static::SUMMARY_UNSUPPORTED_MEDIA_TYPE, [
                static::KEY_STATUS => Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                static::KEY_DETAIL => sprintf(
                    static::DETAIL_UNSUPPORTED_MEDIA_TYPE_FORMAT,
                    static::MIME_TYPE_REQUESTED_IN_EXAMPLE,
                    implode(static::MIME_TYPE_LIST_SEPARATOR, $this->resolveInputMimeTypes($httpOperation)),
                ),
            ]);
        }

        return new ArrayObject($examples);
    }

    public function isProtectedOperation(HttpOperation $httpOperation): bool
    {
        if (($httpOperation->getSecurity() ?? '') !== '') {
            return true;
        }

        return $this->isBearerAuthRequired($httpOperation);
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildBadRequestExamples(?HttpOperation $httpOperation): array
    {
        $examples = [];

        if ($this->isBodyOperation($httpOperation)) {
            $examples[static::EXAMPLE_INVALID_POST_DATA] = $this->createExample(
                static::SUMMARY_INVALID_POST_DATA,
                $this->createDetailBody(Response::HTTP_BAD_REQUEST, JsonApiRequestValidatorSubscriber::ERROR_DETAIL_POST_DATA_INVALID),
            );
            $examples[static::EXAMPLE_INVALID_TYPE] = $this->createExample(
                static::SUMMARY_INVALID_TYPE,
                $this->createDetailBody(Response::HTTP_BAD_REQUEST, JsonApiRequestValidatorSubscriber::ERROR_DETAIL_INVALID_TYPE),
            );
            $examples[static::EXAMPLE_POST_DATA_MISSING] = $this->createExample(static::SUMMARY_POST_DATA_MISSING, [
                static::KEY_CODE => (string)Response::HTTP_BAD_REQUEST,
                static::KEY_STATUS => Response::HTTP_BAD_REQUEST,
                static::KEY_DETAIL => GlueApiExceptionSubscriber::ERROR_DETAIL_BAD_REQUEST,
            ]);
        }

        $examples[static::EXAMPLE_UNSUPPORTED_FILTER_FORMAT] = $this->createExample(static::SUMMARY_UNSUPPORTED_FILTER_FORMAT, [
            static::KEY_CODE => static::ERROR_CODE_UNSUPPORTED_FILTER_FORMAT,
            static::KEY_STATUS => Response::HTTP_BAD_REQUEST,
            static::KEY_MESSAGE => static::MESSAGE_UNSUPPORTED_FILTER_FORMAT,
        ]);

        return $examples;
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildUnauthorizedExamples(?HttpOperation $httpOperation): array
    {
        $resourceExamples = [];
        $resourceSecurityError = $this->resolveResourceSecurityError($httpOperation);

        if ($resourceSecurityError !== null && !$this->isBearerAuthRequired($httpOperation)) {
            $resourceExamples[static::EXAMPLE_UNAUTHENTICATED] = $this->createExample(
                static::SUMMARY_UNAUTHENTICATED,
                $this->createSecurityErrorBody(Response::HTTP_UNAUTHORIZED, $resourceSecurityError),
            );
        }

        if ($httpOperation !== null && !$this->isProtectedOperation($httpOperation)) {
            return $resourceExamples;
        }

        $bearerExamples = [];

        if ($this->isBackendApi()) {
            $bearerExamples[static::EXAMPLE_MISSING_ACCESS_TOKEN] = $this->createExample(static::SUMMARY_MISSING_ACCESS_TOKEN, [
                static::KEY_STATUS => Response::HTTP_UNAUTHORIZED,
                static::KEY_DETAIL => static::DETAIL_AUTHORIZATION_HEADER_REQUIRED,
            ]);
        }

        $bearerExamples[static::EXAMPLE_INVALID_ACCESS_TOKEN] = $this->createExample(static::SUMMARY_INVALID_ACCESS_TOKEN, [
            static::KEY_CODE => OauthAuthenticator::ERROR_CODE_UNAUTHORIZED,
            static::KEY_STATUS => Response::HTTP_UNAUTHORIZED,
            static::KEY_MESSAGE => OauthAuthenticator::ERROR_DETAIL_INVALID_TOKEN,
        ]);

        return $bearerExamples + $resourceExamples;
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildForbiddenExamples(?HttpOperation $httpOperation): array
    {
        $resourceSecurityError = $this->resolveResourceSecurityError($httpOperation);

        if ($httpOperation !== null && !$this->isProtectedOperation($httpOperation)) {
            return $resourceSecurityError === null ? [] : [
                static::EXAMPLE_FORBIDDEN => $this->createExample(
                    static::SUMMARY_FORBIDDEN,
                    $this->createSecurityErrorBody(Response::HTTP_FORBIDDEN, $resourceSecurityError),
                ),
            ];
        }

        $examples = [];

        if (!$this->isBackendApi()) {
            $examples[static::EXAMPLE_MISSING_ACCESS_TOKEN] = $this->createExample(static::SUMMARY_MISSING_ACCESS_TOKEN, [
                static::KEY_CODE => GlueAuthenticationEntryPoint::ERROR_CODE_MISSING_ACCESS_TOKEN,
                static::KEY_STATUS => Response::HTTP_FORBIDDEN,
                static::KEY_DETAIL => GlueAuthenticationEntryPoint::ERROR_DETAIL_MISSING_ACCESS_TOKEN,
            ]);
        }

        $examples[static::EXAMPLE_FORBIDDEN] = $this->createExample(
            static::SUMMARY_FORBIDDEN,
            $this->createSecurityErrorBody(Response::HTTP_FORBIDDEN, $resourceSecurityError ?? $this->createDefaultSecurityError()),
        );

        if ($this->isBackendApi()) {
            $examples[static::EXAMPLE_ACCESS_DENIED_BY_ACL] = $this->createExample(static::DETAIL_ACCESS_DENIED_BY_ACL, [
                static::KEY_STATUS => Response::HTTP_FORBIDDEN,
                static::KEY_DETAIL => static::DETAIL_ACCESS_DENIED_BY_ACL,
            ]);
        }

        return $examples;
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildNotFoundExamples(?HttpOperation $httpOperation): array
    {
        if ($this->isBackendApi()) {
            return [
                static::EXAMPLE_NOT_FOUND => $this->createExample(static::SUMMARY_NOT_FOUND, [
                    static::KEY_CODE => static::ERROR_CODE_RESOURCE_NOT_FOUND_BACKEND,
                    static::KEY_STATUS => Response::HTTP_NOT_FOUND,
                    static::KEY_DETAIL => static::MESSAGE_RESOURCE_NOT_FOUND_BACKEND,
                    static::KEY_MESSAGE => static::MESSAGE_RESOURCE_NOT_FOUND_BACKEND,
                ]),
            ];
        }

        $notFoundError = $httpOperation === null ? null : $this->providerNotFoundErrorResolver->resolveForOperation($httpOperation);

        return [
            static::EXAMPLE_NOT_FOUND => $this->createExample(static::SUMMARY_NOT_FOUND, $notFoundError ?? [
                static::KEY_STATUS => Response::HTTP_NOT_FOUND,
                static::KEY_DETAIL => $this->getReasonPhrase(Response::HTTP_NOT_FOUND),
            ]),
        ];
    }

    /**
     * @param array<string> $requiredAttributes
     *
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildValidationExamples(array $requiredAttributes): array
    {
        $detail = static::DETAIL_VALUE_NOT_VALID;

        if ($requiredAttributes !== []) {
            $detail = sprintf(static::DETAIL_FIELD_MISSING_FORMAT, (string)reset($requiredAttributes));
        }

        return [
            static::EXAMPLE_VALIDATION_FAILED => $this->createExample(static::SUMMARY_VALIDATION_FAILED, [
                static::KEY_CODE => TranslatingConstraintViolationListNormalizer::ERROR_CODE_VALIDATION,
                static::KEY_STATUS => Response::HTTP_UNPROCESSABLE_ENTITY,
                static::KEY_DETAIL => $detail,
            ]),
        ];
    }

    /**
     * @return array<string, \ApiPlatform\OpenApi\Model\Example>
     */
    protected function buildGenericExamples(int $status): array
    {
        return [
            static::EXAMPLE_ERROR => $this->createExample($this->getReasonPhrase($status), [
                static::KEY_STATUS => $status,
                static::KEY_DETAIL => $this->getReasonPhrase($status),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function createDetailBody(int $status, string $detail): array
    {
        return [
            static::KEY_STATUS => $status,
            static::KEY_DETAIL => $detail,
            static::KEY_MESSAGE => $detail,
        ];
    }

    /**
     * @param array<string, string> $resourceSecurityError
     *
     * @return array<string, mixed>
     */
    protected function createSecurityErrorBody(int $status, array $resourceSecurityError): array
    {
        return [
            static::KEY_CODE => $resourceSecurityError[static::KEY_CODE],
            static::KEY_STATUS => $status,
            static::KEY_DETAIL => $resourceSecurityError[static::KEY_DETAIL],
            static::KEY_MESSAGE => $resourceSecurityError[static::KEY_MESSAGE],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function createDefaultSecurityError(): array
    {
        return [
            static::KEY_CODE => GlueApiExceptionSubscriber::ERROR_CODE_UNAUTHORIZED_REQUEST,
            static::KEY_DETAIL => GlueApiExceptionSubscriber::ERROR_DETAIL_UNAUTHORIZED_REQUEST,
            static::KEY_MESSAGE => GlueApiExceptionSubscriber::ERROR_DETAIL_UNAUTHORIZED_REQUEST,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function resolveResourceSecurityError(?HttpOperation $httpOperation): ?array
    {
        $extraProperties = $httpOperation?->getExtraProperties() ?? [];
        $securityCode = $extraProperties[static::EXTRA_PROPERTY_SECURITY_CODE] ?? null;

        if ($securityCode === null) {
            return null;
        }

        $securityMessage = (string)($httpOperation?->getSecurityMessage()
            ?? $extraProperties[static::EXTRA_PROPERTY_SECURITY_MESSAGE]
            ?? GlueApiExceptionSubscriber::ERROR_DETAIL_UNAUTHORIZED_REQUEST);

        return [
            static::KEY_CODE => (string)$securityCode,
            static::KEY_DETAIL => $securityMessage,
            static::KEY_MESSAGE => rtrim($securityMessage, static::TRAILING_PERIOD),
        ];
    }

    /**
     * @return array<string>
     */
    protected function resolveInputMimeTypes(?HttpOperation $httpOperation): array
    {
        $inputMimeTypes = [];

        foreach ($httpOperation?->getInputFormats() ?? [] as $mimeTypes) {
            foreach ((array)$mimeTypes as $mimeType) {
                $inputMimeTypes[] = (string)$mimeType;
            }
        }

        return $inputMimeTypes;
    }

    protected function isBodyOperation(?HttpOperation $httpOperation): bool
    {
        return $httpOperation !== null && in_array(strtoupper((string)$httpOperation->getMethod()), static::HTTP_METHODS_WITH_BODY, true);
    }

    protected function answersUnsupportedMediaType(?HttpOperation $httpOperation): bool
    {
        if (!$this->isBodyOperation($httpOperation) || $httpOperation?->getInput() === false || $httpOperation?->canDeserialize() === false) {
            return false;
        }

        return $this->resolveInputMimeTypes($httpOperation) !== [];
    }

    protected function isBearerAuthRequired(?HttpOperation $httpOperation): bool
    {
        $extraProperties = $httpOperation?->getExtraProperties() ?? [];

        return (bool)($extraProperties[static::EXTRA_PROPERTY_SECURITY_BEARER_AUTH_REQUIRED] ?? false);
    }

    protected function isBackendApi(): bool
    {
        return $this->apiPlatformConfig->hasApiType(ApiPlatformConfig::API_TYPE_BACKEND);
    }

    protected function getReasonPhrase(int $status): string
    {
        return Response::$statusTexts[$status] ?? static::DETAIL_FALLBACK;
    }

    /**
     * @param array<string, mixed> $error
     */
    protected function createExample(string $summary, array $error): Example
    {
        return new Example(summary: $summary, value: [static::KEY_ERRORS => [$error]]);
    }
}
