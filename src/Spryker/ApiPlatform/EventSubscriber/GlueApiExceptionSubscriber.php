<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Intercepts GlueApiException and AccessDeniedException instances and converts them
 * into JSON:API formatted error responses with Glue-compatible `code` field included.
 */
class GlueApiExceptionSubscriber implements EventSubscriberInterface
{
    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string VALIDATORS_DOMAIN = 'validators';

    protected const string ENGLISH_LOCALE = 'en';

    protected const string ERROR_CODE_MISSING_ACCESS_TOKEN = '002';

    protected const string ERROR_DETAIL_MISSING_ACCESS_TOKEN = 'Missing access token.';

    protected const string ERROR_CODE_UNAUTHORIZED_REQUEST = '802';

    protected const string ERROR_DETAIL_UNAUTHORIZED_REQUEST = 'Unauthorized request.';

    protected const string AUTHORIZATION_HEADER = 'Authorization';

    protected const string ANONYMOUS_CUSTOMER_HEADER = 'X-Anonymous-Customer-Unique-Id';

    protected const string ERROR_CODE_CHECKOUT_AUTH_REQUIRED = '1105';

    protected const string ERROR_DETAIL_CHECKOUT_AUTH_REQUIRED = 'One of Authorization or X-Anonymous-Customer-Unique-Id headers is required.';

    protected const string ERROR_DETAIL_BAD_REQUEST = 'Post data missing or invalid.';

    protected const string TYPE_INTEGER_ERROR_MESSAGE = 'This value should be of type integer.';

    public function __construct(
        protected TranslatorInterface $translator,
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        protected bool $debug,
        protected LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @return array<string, array<int, array{string, int}>|array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequestForceEnglishValidation', 9],
                ['onKernelRequest', 0],
            ],
            KernelEvents::EXCEPTION => [
                ['onKernelException', 256],
                ['onKernelExceptionLastResort', -90],
            ],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getMethod() !== 'POST') {
            return;
        }

        if (!$request->attributes->has('_api_resource_class')) {
            return;
        }

        $content = $request->getContent();

        if ($content === '' || $content === '[]' || $content === '{}' || $content === 'null') {
            if ($this->isDeserializationDisabled($request)) {
                return;
            }

            $event->setResponse($this->createJsonApiResponse(
                [
                    'errors' => [
                        [
                            'code' => (string)Response::HTTP_BAD_REQUEST,
                            'status' => Response::HTTP_BAD_REQUEST,
                            'detail' => static::ERROR_DETAIL_BAD_REQUEST,
                        ],
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            ));
        }
    }

    protected function isDeserializationDisabled(Request $request): bool
    {
        $operation = $request->attributes->get('_api_operation');

        if ($operation === null) {
            $resourceClass = $request->attributes->get('_api_resource_class');
            $operationName = $request->attributes->get('_api_operation_name');

            if ($resourceClass === null) {
                return false;
            }

            try {
                $operation = $this->resourceMetadataCollectionFactory->create($resourceClass)->getOperation($operationName);
            } catch (Throwable) {
                return false;
            }
        }

        return is_object($operation)
            && method_exists($operation, 'canDeserialize')
            && $operation->canDeserialize() === false;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof GlueApiException) {
            $event->setResponse($this->createGlueApiErrorResponse($exception));

            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $event->setResponse($this->createAccessDeniedResponse($event));

            return;
        }

        // Convert API Platform deserialization/validation 400 errors to 422
        // for backward compatibility with the old REST API behavior.
        if ($exception instanceof BadRequestHttpException && $event->getRequest()->attributes->has('_api_resource_class')) {
            $event->setResponse($this->createValidationErrorResponse($exception));

            return;
        }

        // PropertyAccessor throws a raw InvalidArgumentException when JSON:API ItemNormalizer
        // tries to assign a non-numeric string to a typed `?int`/`?float` property. Without
        // this branch the kernel renders a 500. Legacy Glue returned 422 / code 901 with
        // "<property> => This value should be of type numeric." — restore that contract.
        if ($event->getRequest()->attributes->has('_api_resource_class')) {
            $propertyTypeError = $this->matchPropertyTypeError($exception->getMessage());

            if ($propertyTypeError !== null) {
                $event->setResponse($this->createJsonApiResponse(
                    [
                        'errors' => [
                            [
                                'code' => static::ERROR_CODE_VALIDATION,
                                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                                'detail' => $propertyTypeError,
                            ],
                        ],
                    ],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                ));

                return;
            }
        }

        // Convert routing-level 405 to 404 for backward compatibility with the old Glue REST API,
        // which returned 404 for unsupported HTTP methods. This only applies when _api_resource_class
        // is not set (i.e. Symfony Router rejected the method before API Platform resolved the resource).
        // 405 errors thrown explicitly from providers/processors (with _api_resource_class set) are kept as-is.
        if ($exception instanceof MethodNotAllowedHttpException && !$event->getRequest()->attributes->has('_api_resource_class')) {
            $event->setResponse($this->createHttpExceptionResponse(new NotFoundHttpException(), $event->getRequest()));

            return;
        }

        // Convert generic HTTP exceptions to JSON:API format to prevent Symfony's ErrorListener
        // from rendering HTML error pages. This handles both API Platform routes (with _api_resource_class)
        // and unmatched routes (e.g. unsupported HTTP methods like POST/PATCH on read-only resources).
        //
        // Exception: skip when this is an ApiApplicationProxy fallback request (api-platform-request=true)
        // that did not match any API Platform resource. In that case the exception must propagate so that
        // kernel::handle() throws and ApiApplicationProxy's Throwable catch preserves the original Glue
        // error response (e.g. DynamicEntityBackendApi returns code 007 in application/json format).
        if ($exception instanceof HttpExceptionInterface) {
            $isNonApiPlatformFallback = $event->getRequest()->attributes->get('api-platform-request') === true
                && !$event->getRequest()->attributes->has('_api_resource_class');

            if (!$isNonApiPlatformFallback) {
                $event->setResponse($this->createHttpExceptionResponse($exception, $event->getRequest()));
            }
        }
    }

    /**
     * Last-resort guard against leaking exception details on API Platform requests.
     *
     * Runs after the application exception subscribers (priority 256 above,
     * OAuthExceptionSubscriber at 10) but before API Platform's own exception
     * listener (-96) and Symfony's ErrorListener (-128), both of which can embed
     * the message, class name, file path, and stack trace in the response.
     *
     * In production ($debug = false) any uncaught non-HTTP throwable on a resolved
     * API Platform request is replaced with a generic 500 so traces never reach the
     * client. In development ($debug = true) the guard steps aside and lets the
     * throwable propagate to API Platform's debug error renderer, so the developer
     * sees the full message, file and stack trace right away.
     *
     * HTTP exceptions are intentionally skipped: direct ones are already handled
     * at priority 256, and the OAuthExceptionSubscriber-converted one keeps its
     * status code through API Platform's renderer.
     */
    public function onKernelExceptionLastResort(ExceptionEvent $event): void
    {
        if ($this->debug) {
            return;
        }

        if ($event->getResponse() !== null) {
            return;
        }

        if (!$event->getRequest()->attributes->has('_api_resource_class')) {
            return;
        }

        if ($event->getThrowable() instanceof HttpExceptionInterface) {
            return;
        }

        // The throwable is about to be sanitised into a generic 500 with no body, so it would
        // otherwise vanish without a trace (API Platform's error pipeline does not route it to
        // the application logger). Log it here so operators are not blind to the real cause.
        $this->logUncaughtThrowable($event->getThrowable(), $event->getRequest());

        $event->setResponse($this->createInternalServerErrorResponse());
    }

    protected function logUncaughtThrowable(Throwable $throwable, Request $request): void
    {
        $this->logger->error(
            sprintf(
                'Uncaught exception on API Platform request "%s %s": %s',
                $request->getMethod(),
                $request->getPathInfo(),
                $throwable->getMessage(),
            ),
            ['exception' => $throwable],
        );
    }

    /**
     * BC: legacy Spryker Glue REST framework returned `text/html` + plain "Internal Server Error"
     * body for uncaught exceptions; consumers (incl. Robot's `I send a POST request:` keyword)
     * rely on this shape to detect 500s by failed JSON parsing. All explicitly handled errors
     * (4xx, GlueApiException-derived 5xx) still go through `createGlueApiErrorResponse()` /
     * `createHttpExceptionResponse()` and return the JSON:API envelope — only the *uncaught*
     * last-resort fallback keeps the legacy `text/html` format.
     */
    protected function createInternalServerErrorResponse(): Response
    {
        return new Response(
            Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR] ?? 'Internal Server Error',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    protected function createGlueApiErrorResponse(GlueApiException $exception): JsonResponse
    {
        $errors = $exception->getErrors();
        foreach ($errors as &$errorItem) {
            if (!isset($errorItem['message'])) {
                $errorItem['message'] = (string)$errorItem['detail'];
            }
        }

        if ($errors === []) {
            $error = [];

            if ($exception->getErrorCode() !== '') {
                $error['code'] = $exception->getErrorCode();
            }

            $error['status'] = $exception->getStatusCode();
            $error['detail'] = $exception->getMessage();
            $error['message'] = $exception->getMessage();

            $errors = [$error];
        }

        return $this->createJsonApiResponse(['errors' => $errors], $exception->getStatusCode());
    }

    /**
     * Converts API Platform's 400 deserialization errors to 422 with Spryker error code 901,
     * preserving backward compatibility with the old REST API validation error format.
     *
     * @return void
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Fix JSON encoding for all JSON:API error responses to prevent escaped
        // unicode sequences (\u003E for >, \u0027 for ') that break test assertions.
        $this->fixJsonEncoding($response, $request);

        // Promote relative link URLs to absolute using the request base URL.
        // API Platform's CollectionNormalizer generates ABS_PATH links (/path) by default.
        // The old Glue REST API always returned fully-qualified URLs (http://host/path).
        $this->fixRelativeLinks($response, $request);

        // Augment 422 validation responses with missing Type/GreaterThan errors for empty-string numeric fields.
        // API Platform converts empty strings to null for typed properties (e.g. ?int), which causes Type and
        // comparison constraints to pass. The old REST API validated raw strings, so all errors were returned.
        // Enrich generic 404 responses from API Platform with domain-specific error messages.
        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && $request->attributes->has('_api_resource_class')) {
            $this->enrichNotFoundResponse($response, $request);
        }

        if ($response->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY && $request->attributes->has('_api_resource_class')) {
            $this->normalizeValidationErrorFormat($response, $request);

            $resourceClass = (string)$request->attributes->get('_api_resource_class', '');

            if ($resourceClass !== '') {
                $this->augmentValidationErrorsForEmptyStringValues($response, $request, $resourceClass);
                $this->augmentValidationErrorsForStringNumericValues($response, $request, $resourceClass);
                $this->augmentValidationErrorsForBoolFields($response, $request, $resourceClass);
            }
        }

        if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST || !$request->attributes->has('_api_resource_class')) {
            return;
        }

        $content = $response->getContent();

        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['errors'])) {
            return;
        }

        // Only convert deserialization errors (contain "denormalize" in detail)
        $firstError = $data['errors'][0] ?? [];
        $detail = $firstError['detail'] ?? '';

        if (!str_contains($detail, 'denormalize') && !str_contains($detail, 'Syntax error')) {
            return;
        }

        $errors = [];

        foreach ($data['errors'] as $error) {
            $detail = $error['detail'] ?? $error['title'] ?? 'Validation error.';

            $errors[] = [
                'code' => '901',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => $this->transformDenormalizationMessage($detail),
            ];
        }

        $event->setResponse($this->createJsonApiResponse(
            ['errors' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    }

    protected function createValidationErrorResponse(BadRequestHttpException $exception): JsonResponse
    {
        return $this->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'code' => '901',
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                        'detail' => $this->transformDenormalizationMessage($exception->getMessage()),
                    ],
                ],
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * Re-encodes JSON responses to use unescaped unicode and slashes, preventing
     * escaped sequences like \u003E (for >) and \u0027 (for ') that confuse
     * JSON parsers in test frameworks.
     */
    protected function fixJsonEncoding(Response $response, Request $request): void
    {
        if (!$request->attributes->has('_api_resource_class')) {
            return;
        }

        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_contains($contentType, 'json')) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Promotes relative link URLs in JSON:API responses to absolute URLs.
     * API Platform's CollectionNormalizer generates ABS_PATH links (/path) by default.
     * Walks all string values under `links` keys at any nesting level and prepends scheme+host.
     */
    protected function fixRelativeLinks(Response $response, Request $request): void
    {
        if (!$request->attributes->has('_api_resource_class')) {
            return;
        }

        $contentType = $response->headers->get('Content-Type') ?? '';

        if (!str_contains($contentType, 'json')) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            return;
        }

        $scheme = $request->getScheme();
        $host = $request->getHttpHost();
        $baseUrl = $scheme . '://' . $host;

        $this->promoteRelativeLinks($data, $baseUrl);

        $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function promoteRelativeLinks(array &$data, string $baseUrl): void
    {
        if (isset($data['links']) && is_array($data['links'])) {
            foreach ($data['links'] as &$link) {
                if (is_string($link) && str_starts_with($link, '/')) {
                    $link = $baseUrl . $link;
                }
            }
            unset($link);
        }

        // Recurse into data items and included resources
        foreach (['data', 'included'] as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }

            if (isset($data[$key]['links'])) {
                // Single resource
                $this->promoteRelativeLinks($data[$key], $baseUrl);
            } else {
                // Collection of items
                foreach ($data[$key] as &$item) {
                    if (is_array($item)) {
                        $this->promoteRelativeLinks($item, $baseUrl);
                    }
                }
                unset($item);
            }
        }
    }

    protected function createHttpExceptionResponse(HttpExceptionInterface $exception, Request $request): JsonResponse
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage();
        $detail = ($message !== '' && $message !== (Response::$statusTexts[$statusCode] ?? ''))
            ? $message
            : (Response::$statusTexts[$statusCode] ?? 'Error');

        $error = [
            'status' => $statusCode,
            'detail' => $detail,
        ];

        // For 404 responses, check if the resource class has a domain-specific
        // "not found" error code and message defined as class constants.
        if ($statusCode === Response::HTTP_NOT_FOUND) {
            $resourceClass = (string)$request->attributes->get('_api_resource_class', '');
            $notFoundError = $this->resolveProviderNotFoundError($resourceClass);

            if ($notFoundError !== null) {
                $error = $notFoundError;
            }
        }

        return $this->createJsonApiResponse(['errors' => [$error]], $statusCode);
    }

    /**
     * Resolves domain-specific "not found" error from the resource's provider class.
     * Checks for ERROR_CODE_*_NOT_FOUND and ERROR_MESSAGE_*_NOT_FOUND constants.
     *
     * @return array{code: string, status: int, detail: string, message: string}|null
     */
    protected function resolveProviderNotFoundError(string $resourceClass): ?array
    {
        if ($resourceClass === '' || !class_exists($resourceClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($resourceClass);
            $attributes = $reflection->getAttributes(ApiResource::class);

            if ($attributes === []) {
                return null;
            }

            $apiResource = $attributes[0]->newInstance();
            $providerClass = $apiResource->getProvider();

            if (!is_string($providerClass) || !class_exists($providerClass)) {
                return null;
            }

            $providerReflection = new ReflectionClass($providerClass);

            foreach ($providerReflection->getReflectionConstants() as $constant) {
                if (!str_contains($constant->getName(), 'NOT_FOUND') || !str_contains($constant->getName(), 'MESSAGE')) {
                    continue;
                }

                $codeConstantName = str_replace('MESSAGE', 'CODE', $constant->getName());

                if (!$providerReflection->hasConstant($codeConstantName)) {
                    continue;
                }

                $message = (string)$constant->getValue();
                $code = (string)$providerReflection->getConstant($codeConstantName);

                return [
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => $message,
                    'message' => $message,
                    'code' => $code,
                ];
            }
        } catch (Throwable) {
            // Reflection failures should not break error handling
        }

        return null;
    }

    protected function createJsonApiResponse(array $data, int $statusCode): JsonResponse
    {
        $response = new JsonResponse(null, $statusCode);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->setData($data);
        $response->headers->set('Content-Type', static::CONTENT_TYPE_JSON_API);

        return $response;
    }

    /**
     * @var array<string>
     *
     * Fallback synthetic errors for empty-string values sent to numeric properties
     * when no Type/comparison constraints are declared on the property.
     */
    protected const array NUMERIC_EMPTY_STRING_ERRORS_FALLBACK = [
        'This value should be of type numeric.',
        'This value should be greater than 0.',
    ];

    protected const string ERROR_CODE_VALIDATION = '901';

    protected const string FIELD_MISSING_MESSAGE = 'This field is missing.';

    protected const string BOOL_SHOULD_BE_TRUE_MESSAGE = 'This value should be true.';

    /**
     * Forces the translator locale to English for validation messages on API Platform routes.
     * The old REST API returned English because validation ran before locale was set.
     * Only applies to API Platform routes to avoid affecting legacy Glue endpoints.
     *
     * @return void
     */
    public function onKernelRequestForceEnglishValidation(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only force English for API Platform routes — legacy Glue already returns English
        // because the old GlueApplication sets locale AFTER validation.
        if (!$event->getRequest()->attributes->has('_api_resource_class')) {
            return;
        }

        if ($this->translator instanceof LocaleAwareInterface) {
            $this->translator->setLocale(static::ENGLISH_LOCALE);
        }
    }

    protected function enrichNotFoundResponse(Response $response, Request $request): void
    {
        $content = $response->getContent();

        // Do not overwrite responses that already have domain-specific error codes
        // (e.g., from GlueApiException). Only enrich generic API Platform 404 responses.
        if ($content !== false && $content !== '') {
            $data = json_decode($content, true);

            if (is_array($data) && isset($data['errors'][0]['code']) && $data['errors'][0]['code'] !== '404') {
                return;
            }
        }

        $resourceClass = (string)$request->attributes->get('_api_resource_class', '');
        $notFoundError = $this->resolveProviderNotFoundError($resourceClass);

        if ($notFoundError === null) {
            return;
        }

        $response->setContent((string)json_encode(
            ['errors' => [$notFoundError]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * Splits single-error validation responses (where all violations are concatenated
     * with newlines in one detail string) into separate error objects with code 901
     * and "property => message" format, matching the old REST API behavior.
     *
     * @return void
     */
    protected function normalizeValidationErrorFormat(Response $response, Request $request): void
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['errors'])) {
            return;
        }

        // Check if errors need reformatting: single error with "property: message" format.
        // Property may be a nested path produced by Collection/All constraints, e.g.
        // `parent[child][0][leaf]` — accept word characters and bracket-segment notation.
        if (count($data['errors']) !== 1) {
            return;
        }

        $detail = $data['errors'][0]['detail'] ?? '';

        if (!is_string($detail) || $detail === '' || !preg_match('/^[\w\[\]]+: /', $detail)) {
            return;
        }

        $submittedFields = $this->resolveSubmittedFields($request);
        $lines = array_filter(explode("\n", $detail), static fn (string $line): bool => $line !== '');
        $errors = [];

        foreach ($lines as $line) {
            $colonPos = strpos($line, ': ');
            $fieldName = $colonPos !== false ? substr($line, 0, $colonPos) : null;
            $message = $colonPos !== false ? substr($line, $colonPos + 2) : $line;

            if ($fieldName !== null) {
                // Convert Symfony's bracket path notation `parent[child][0]` to Spryker's
                // dot notation `parent.child.0` to match legacy Glue REST error format.
                $fieldName = $this->normalizePropertyPath($fieldName);
            }

            // Detect missing fields: "not blank/null" errors for fields not submitted in the request.
            // Only top-level fields are considered submitted — nested paths bypass this check.
            if (
                $fieldName !== null
                && $submittedFields !== null
                && !str_contains($fieldName, '.')
                && !in_array($fieldName, $submittedFields, true)
            ) {
                $message = 'This field is missing.';
            }

            $formattedDetail = $fieldName !== null
                ? sprintf('%s => %s', $fieldName, $message)
                : $message;

            $errors[] = [
                'code' => '901',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => $formattedDetail,
            ];
        }

        $response->setContent((string)json_encode(
            ['errors' => $errors],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * Converts Symfony Validator's bracket property-path notation to Spryker's dot notation.
     * Examples:
     *  - `parent` → `parent`
     *  - `parent[child]` → `parent.child`
     *  - `parent[items][0][sku]` → `parent.items.0.sku`
     */
    protected function normalizePropertyPath(string $propertyPath): string
    {
        return rtrim(str_replace(['[', ']'], ['.', ''], $propertyPath), '.');
    }

    /**
     * @return array<string>|null
     */
    protected function resolveSubmittedFields(Request $request): ?array
    {
        $body = json_decode((string)$request->getContent(), true);

        if (!is_array($body) || !isset($body['data']['attributes'])) {
            return null;
        }

        return array_map('strval', array_keys($body['data']['attributes']));
    }

    /**
     * Detects numeric-typed properties on the resource class that have NotBlank errors
     * but are missing Type and comparison constraint errors. This happens when API Platform
     * converts empty strings to null for typed properties (e.g. ?int) before validation runs.
     * The old REST API validated raw strings, so all constraints fired.
     *
     * @return void
     */
    protected function augmentValidationErrorsForEmptyStringValues(Response $response, Request $request, string $resourceClass): void
    {
        if (!class_exists($resourceClass)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['errors'])) {
            return;
        }

        $existingDetails = [];
        $fieldsWithNotBlankOnly = [];

        foreach ($data['errors'] as $error) {
            $detail = $error['detail'] ?? '';
            $existingDetails[$detail] = true;

            if (preg_match('/^(\w+) => This value should not be blank\.$/', $detail, $matches)) {
                $fieldsWithNotBlankOnly[$matches[1]] = true;
            }
        }

        // Remove fields that already have additional errors beyond NotBlank
        foreach ($data['errors'] as $error) {
            $detail = $error['detail'] ?? '';

            if (preg_match('/^(\w+) => (?!This value should not be blank\.$)/', $detail, $matches)) {
                unset($fieldsWithNotBlankOnly[$matches[1]]);
            }
        }

        if ($fieldsWithNotBlankOnly === []) {
            return;
        }

        $groups = $this->getActiveValidationGroups($request);
        $modified = false;

        foreach (array_keys($fieldsWithNotBlankOnly) as $fieldName) {
            if (!$this->isNumericProperty($resourceClass, $fieldName)) {
                continue;
            }

            $messages = $this->buildSyntheticErrorsForEmptyNumericProperty($resourceClass, $fieldName, $groups);

            if ($messages === []) {
                $messages = static::NUMERIC_EMPTY_STRING_ERRORS_FALLBACK;
            }

            foreach ($messages as $errorMessage) {
                $detail = sprintf('%s => %s', $fieldName, $errorMessage);

                if (isset($existingDetails[$detail])) {
                    continue;
                }

                $data['errors'][] = [
                    'detail' => $detail,
                    'code' => '901',
                    'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                ];

                $existingDetails[$detail] = true;
                $modified = true;
            }
        }

        if ($modified) {
            $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Restores validation errors that the legacy REST API produced for non-empty string values
     * submitted to typed integer fields. Two cases arise:
     *
     * 1. Numeric string (e.g. "-2"): AP coerces it to int before validation, so the Type constraint
     *    passes on the already-cast value. "This value should be of type integer." is missing and
     *    must be prepended to the existing comparison-constraint errors.
     *
     * 2. Non-numeric string (e.g. "test"): AP's PropertyAccessor cannot coerce the value and throws,
     *    which the exception handler converts to a single "type numeric" error — bypassing Symfony
     *    Validator entirely. "type numeric" must be replaced by "type integer", and any comparison
     *    constraints that would have fired against the raw string (using PHP 8 semantics) are added.
     */
    protected function augmentValidationErrorsForStringNumericValues(Response $response, Request $request, string $resourceClass): void
    {
        if (!class_exists($resourceClass)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['errors'])) {
            return;
        }

        $rawAttributes = $this->extractRawAttributes($request);
        $groups = $this->getActiveValidationGroups($request);
        $modified = false;

        foreach (array_keys($rawAttributes) as $fieldName) {
            $rawValue = $rawAttributes[$fieldName];

            if (!$this->isStringOrIntegerOverflowValue($rawValue)) {
                continue;
            }

            if (!$this->isNumericProperty($resourceClass, $fieldName)) {
                continue;
            }

            $typeNumericDetail = sprintf('%s => %s', $fieldName, static::NUMERIC_EMPTY_STRING_ERRORS_FALLBACK[0]);
            $typeIntegerDetail = sprintf('%s => %s', $fieldName, static::TYPE_INTEGER_ERROR_MESSAGE);

            $existingDetails = array_column($data['errors'], 'detail');
            $hasTypeNumeric = in_array($typeNumericDetail, $existingDetails, true);
            $hasTypeInteger = in_array($typeIntegerDetail, $existingDetails, true);

            if ($hasTypeInteger) {
                continue;
            }

            if ($hasTypeNumeric) {
                $modified = $this->replaceTypeNumericWithTypeIntegerError($data['errors'], $resourceClass, $fieldName, (string)$rawValue, $groups, $typeNumericDetail, $typeIntegerDetail) || $modified;

                continue;
            }

            if (is_string($rawValue) && $rawValue !== '') {
                $modified = $this->prependTypeIntegerErrorForNumericString($data['errors'], $resourceClass, $fieldName, $rawValue, $groups) || $modified;
            }
        }

        if ($modified) {
            $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractRawAttributes(Request $request): array
    {
        $rawBody = json_decode((string)$request->getContent(), true);

        return is_array($rawBody) && isset($rawBody['data']['attributes']) && is_array($rawBody['data']['attributes'])
            ? $rawBody['data']['attributes']
            : [];
    }

    /**
     * Returns true for non-empty strings and for floats that result from JSON integer overflow
     * (e.g. 99999999999999999999 decoded as float). PropertyAccessor cannot assign a float to ?int,
     * so the exception handler produces "type numeric" — both cases need the same replacement logic.
     */
    protected function isStringOrIntegerOverflowValue(mixed $value): bool
    {
        return (is_string($value) && $value !== '') || is_float($value);
    }

    /**
     * Replaces the generic "type numeric" error with "type integer" and appends any
     * comparison constraint violations for fields where the Type constraint declares integer.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function replaceTypeNumericWithTypeIntegerError(
        array &$errors,
        string $resourceClass,
        string $fieldName,
        string $rawValue,
        array $groups,
        string $typeNumericDetail,
        string $typeIntegerDetail,
    ): bool {
        $declaredType = $this->getTypeConstraintTypeName($resourceClass, $fieldName, $groups);

        if ($declaredType !== 'integer' && $declaredType !== 'int') {
            return false;
        }

        $errors = array_values(array_filter(
            $errors,
            static fn (array $e): bool => ($e['detail'] ?? '') !== $typeNumericDetail,
        ));

        array_unshift($errors, [
            'detail' => $typeIntegerDetail,
            'code' => static::ERROR_CODE_VALIDATION,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);

        $this->appendComparisonConstraintErrors($errors, $resourceClass, $fieldName, $rawValue, $groups);

        return true;
    }

    /**
     * Prepends a "type integer" error when the raw numeric string (e.g. "-2") fails the
     * Assert\Type constraint declared on the property. API Platform silently coerced the value
     * before validation, so the constraint never fired — this restores the legacy behavior.
     *
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function prependTypeIntegerErrorForNumericString(
        array &$errors,
        string $resourceClass,
        string $fieldName,
        string $rawValue,
        array $groups,
    ): bool {
        $typeError = $this->buildTypeErrorForRawStringValue($resourceClass, $fieldName, $rawValue, $groups);

        if ($typeError === null) {
            return false;
        }

        array_unshift($errors, [
            'detail' => sprintf('%s => %s', $fieldName, $typeError),
            'code' => static::ERROR_CODE_VALIDATION,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
        ]);

        return true;
    }

    /**
     * @param array<array<string, mixed>> $errors
     * @param array<string> $groups
     */
    protected function appendComparisonConstraintErrors(array &$errors, string $resourceClass, string $fieldName, string $rawValue, array $groups): void
    {
        foreach ($this->evaluateComparisonConstraints($resourceClass, $fieldName, $rawValue, $groups) as $errorDetail) {
            $errors[] = [
                'detail' => sprintf('%s => %s', $fieldName, $errorDetail),
                'code' => static::ERROR_CODE_VALIDATION,
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ];
        }
    }

    /**
     * @param array<string> $groups
     */
    protected function buildTypeErrorForRawStringValue(string $resourceClass, string $fieldName, string $rawValue, array $groups): ?string
    {
        $constraint = $this->getTypeConstraintInstance($resourceClass, $fieldName, $groups);

        if ($constraint === null || !isset($constraint->type, $constraint->message)) {
            return null;
        }

        $type = is_array($constraint->type) ? $constraint->type[0] : (string)$constraint->type;

        $passes = match ($type) {
            'integer', 'int' => false,
            'numeric' => is_numeric($rawValue),
            'float', 'double' => false,
            'string' => true,
            'bool', 'boolean' => false,
            default => true,
        };

        if ($passes) {
            return null;
        }

        return strtr((string)$constraint->message, ['{{ type }}' => $type]);
    }

    /**
     * Evaluates GreaterThan and LessThan constraints against the raw submitted string value
     * using PHP 8 comparison semantics (non-numeric strings are compared as strings after
     * converting the comparand to string). Returns the message for each failing constraint.
     *
     * @param array<string> $groups
     *
     * @return array<string>
     */
    protected function evaluateComparisonConstraints(string $resourceClass, string $fieldName, string $rawValue, array $groups): array
    {
        $errors = [];

        foreach ($this->getPropertyConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            if (!$constraint instanceof GreaterThan && !$constraint instanceof LessThan) {
                continue;
            }

            if (!isset($constraint->value, $constraint->message)) {
                continue;
            }

            $comparand = $constraint->value;
            $violated = $constraint instanceof GreaterThan
                ? !($rawValue > $comparand)
                : !($rawValue < $comparand);

            if (!$violated) {
                continue;
            }

            $comparandString = is_scalar($constraint->value) ? (string)$constraint->value : '';
            $msg = strtr((string)$constraint->message, ['{{ compared_value }}' => $comparandString]);

            if (!in_array($msg, $errors, true)) {
                $errors[] = $msg;
            }
        }

        return $errors;
    }

    /**
     * @param array<string> $groups
     */
    protected function getTypeConstraintTypeName(string $resourceClass, string $fieldName, array $groups): ?string
    {
        $constraint = $this->getTypeConstraintInstance($resourceClass, $fieldName, $groups);

        if ($constraint === null || !isset($constraint->type)) {
            return null;
        }

        return is_array($constraint->type) ? $constraint->type[0] : (string)$constraint->type;
    }

    /**
     * @return array<\ReflectionAttribute<object>>
     */
    protected function getPropertyAttributes(string $resourceClass, string $fieldName): array
    {
        if (!property_exists($resourceClass, $fieldName)) {
            return [];
        }

        /** @phpstan-var class-string $resourceClass */
        return (new ReflectionProperty($resourceClass, $fieldName))->getAttributes();
    }

    /**
     * Returns instantiated constraints for a property, filtered to those that apply to the given
     * validation groups. When $groups is empty all constraints are returned.
     *
     * @param array<string> $groups
     *
     * @return array<\Symfony\Component\Validator\Constraint>
     */
    protected function getPropertyConstraintsForGroups(string $resourceClass, string $fieldName, array $groups): array
    {
        $constraints = [];

        foreach ($this->getPropertyAttributes($resourceClass, $fieldName) as $attribute) {
            try {
                $constraint = $attribute->newInstance();
            } catch (Throwable) {
                continue;
            }

            if (!$constraint instanceof Constraint) {
                continue;
            }

            if ($groups !== [] && array_intersect($constraint->groups, $groups) === []) {
                continue;
            }

            $constraints[] = $constraint;
        }

        return $constraints;
    }

    /**
     * @return array<string>
     */
    protected function getActiveValidationGroups(Request $request): array
    {
        $operation = $request->attributes->get('_api_operation');

        if (!$operation instanceof Operation) {
            return [];
        }

        return $operation->getValidationContext()['groups'] ?? [];
    }

    /**
     * @param array<string> $groups
     */
    protected function getTypeConstraintInstance(string $resourceClass, string $fieldName, array $groups): ?Type
    {
        foreach ($this->getPropertyConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            if ($constraint instanceof Type) {
                return $constraint;
            }
        }

        return null;
    }

    protected function isNumericProperty(string $resourceClass, string $propertyName): bool
    {
        if (!property_exists($resourceClass, $propertyName)) {
            return false;
        }

        /** @phpstan-var class-string $resourceClass */
        $type = (new ReflectionProperty($resourceClass, $propertyName))->getType();

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        return in_array($type->getName(), ['int', 'float'], true);
    }

    /**
     * @param array<string> $groups
     *
     * @return array<int, string>
     */
    protected function buildSyntheticErrorsForEmptyNumericProperty(string $resourceClass, string $fieldName, array $groups): array
    {
        $errors = [];

        foreach ($this->getPropertyConstraintsForGroups($resourceClass, $fieldName, $groups) as $constraint) {
            $message = $this->renderConstraintMessage($constraint);

            if ($message !== null) {
                $errors[] = $message;
            }
        }

        return $errors;
    }

    protected function renderConstraintMessage(Constraint $constraint): ?string
    {
        if ($constraint instanceof Type) {
            $types = is_array($constraint->type) ? implode('|', $constraint->type) : (string)$constraint->type;

            return strtr($constraint->message, ['{{ type }}' => $types]);
        }

        if ($constraint instanceof AbstractComparison) {
            return strtr($constraint->message, [
                '{{ compared_value }}' => $this->formatConstraintValue($constraint->value),
            ]);
        }

        if ($constraint instanceof Range) {
            return strtr($constraint->notInRangeMessage, [
                '{{ min }}' => $this->formatConstraintValue($constraint->min),
                '{{ max }}' => $this->formatConstraintValue($constraint->max),
            ]);
        }

        return null;
    }

    protected function formatConstraintValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }

    protected function isRequiredApiProperty(ReflectionProperty $property): bool
    {
        foreach ($property->getAttributes() as $attribute) {
            if ($attribute->getName() !== 'ApiPlatform\Metadata\ApiProperty') {
                continue;
            }

            $args = $attribute->getArguments();

            return isset($args['required']) && $args['required'] === true;
        }

        return false;
    }

    /**
     * Augments 422 validation responses with missing errors for nullable bool properties.
     *
     * API Platform converts empty strings to null for ?bool typed properties, and IsTrue
     * skips null values. This method restores the expected errors by inspecting the raw
     * request body:
     * - Field absent entirely → "This field is missing." (required field not provided)
     * - Field submitted as empty string or null → "This value should be true." (non-boolean value)
     *
     * Note: when the response is already 422 (other fields invalid), any non-true bool
     * value should be reported as a validation error. When all other fields are valid and
     * only a bool field is null, the response is not 422, so this method does not run
     * and the processor handles the domain-specific error (e.g. 413 for unaccepted terms).
     *
     * Only applies to POST requests — bool fields like acceptedTerms are only required
     * on create operations, not on PATCH/PUT where they are excluded from validation groups.
     */
    protected function augmentValidationErrorsForBoolFields(Response $response, Request $request, string $resourceClass): void
    {
        if ($request->getMethod() !== 'POST') {
            return;
        }

        if (!class_exists($resourceClass)) {
            return;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['errors'])) {
            return;
        }

        $rawBody = json_decode((string)$request->getContent(), true);
        $attributes = is_array($rawBody) && isset($rawBody['data']['attributes']) && is_array($rawBody['data']['attributes'])
            ? $rawBody['data']['attributes']
            : [];

        $existingDetails = [];

        foreach ($data['errors'] as $error) {
            $existingDetails[$error['detail'] ?? ''] = true;
        }

        $modified = false;

        $reflectionClass = new ReflectionClass($resourceClass);

        foreach ($reflectionClass->getProperties() as $property) {
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType || $type->getName() !== 'bool' || !$type->allowsNull()) {
                continue;
            }

            if (!$this->isRequiredApiProperty($property)) {
                continue;
            }

            $fieldName = $property->getName();

            if (!array_key_exists($fieldName, $attributes)) {
                $detail = sprintf('%s => %s', $fieldName, static::FIELD_MISSING_MESSAGE);

                if (!isset($existingDetails[$detail])) {
                    $data['errors'][] = ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
                    $existingDetails[$detail] = true;
                    $modified = true;
                }

                continue;
            }

            if ($attributes[$fieldName] === '' || $attributes[$fieldName] === null) {
                $detail = sprintf('%s => %s', $fieldName, static::BOOL_SHOULD_BE_TRUE_MESSAGE);

                if (!isset($existingDetails[$detail])) {
                    $data['errors'][] = ['detail' => $detail, 'code' => static::ERROR_CODE_VALIDATION, 'status' => Response::HTTP_UNPROCESSABLE_ENTITY];
                    $existingDetails[$detail] = true;
                    $modified = true;
                }
            }
        }

        if ($modified) {
            $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Matches the PropertyAccessor message
     * `Expected argument of type "<type>", "<given>" given at property path "<path>".`
     * and returns the legacy-formatted detail (or null when not a match).
     *
     * Numeric target types (`int`, `float`, optional variants) map to `should be of type numeric.`;
     * other targets fall back to `should be of type <type>.` mirroring legacy Glue behaviour.
     */
    protected function matchPropertyTypeError(string $message): ?string
    {
        if (!preg_match('/Expected argument of type "(\??\w+)", "[^"]+" given at property path "([\w\.\[\]]+)"/', $message, $matches)) {
            return null;
        }

        $expectedType = ltrim($matches[1], '?');
        $propertyPath = $this->normalizePropertyPath($matches[2]);
        $reportedType = in_array($expectedType, ['int', 'integer', 'float', 'double'], true) ? 'numeric' : $expectedType;

        return sprintf('%s => This value should be of type %s.', $propertyPath, $reportedType);
    }

    /**
     * Transforms raw API Platform denormalization error messages into the Spryker
     * validation format: "propertyName => This value should be of type numeric."
     *
     * For example, the raw message:
     *   Failed to denormalize attribute "quantity" value for class "...": Expected argument of type "?int", "string" given ...
     * Becomes:
     *   quantity => This value should be of type numeric.
     */
    protected function transformDenormalizationMessage(string $message): string
    {
        if (!preg_match('/denormalize attribute "(\w+)".*Expected argument of type/', $message, $matches)) {
            return $message;
        }

        $propertyName = $matches[1];

        return sprintf('%s => This value should be of type numeric.', $propertyName);
    }

    protected function createAccessDeniedResponse(ExceptionEvent $event): JsonResponse
    {
        $request = $event->getRequest();
        $authorizationValue = (string)$request->headers->get(static::AUTHORIZATION_HEADER, '');
        $hasValidBearerToken = str_starts_with($authorizationValue, 'Bearer ') && strlen($authorizationValue) > 7;
        $hasAnonymousCustomerHeader = $request->headers->has(static::ANONYMOUS_CUSTOMER_HEADER);

        $resourceClass = (string)$request->attributes->get('_api_resource_class', '');
        $extraProperties = $this->resolveResourceExtraProperties($resourceClass);

        // Resources that accept either bearer or anonymous customer auth (e.g. checkout)
        // return 400 with a dedicated code when neither header is present — UNLESS the access denial
        // was raised by the CustomerAccess voter (b2b projects restricting `order-place-submit`/`price`
        // content types). In that case the legacy 403/002 "Missing access token." response takes
        // precedence so customer-access protection keeps behaving the way Glue REST did before the
        // API Platform migration. The flag is set in
        // {@see \Spryker\Glue\CustomerAccessRestApi\Api\Storefront\Security\CustomerAccessVoter}.
        $isCustomerAccessDenied = (bool)$request->attributes->get('_customer_access_denied', false);

        if (!$hasValidBearerToken && !$hasAnonymousCustomerHeader && ($extraProperties['securityAnonymousAuthRequired'] ?? false) && !$isCustomerAccessDenied) {
            return $this->createJsonApiResponse(
                [
                    'errors' => [
                        [
                            'code' => static::ERROR_CODE_CHECKOUT_AUTH_REQUIRED,
                            'status' => Response::HTTP_BAD_REQUEST,
                            'detail' => static::ERROR_DETAIL_CHECKOUT_AUTH_REQUIRED,
                        ],
                    ],
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $resourceSecurityError = $this->resolveResourceSecurityError($extraProperties);

        // Unauthenticated request to a resource with custom security that does not use
        // bearer tokens (e.g. agent endpoints) — return 401 with the resource's error code.
        // Resources requiring bearer auth skip this path and fall through
        // to the standard "Missing access token" response below.
        if ($resourceSecurityError !== null && !($extraProperties['securityBearerAuthRequired'] ?? false)) {
            return $this->createJsonApiResponse(
                [
                    'errors' => [
                        [
                            'code' => $resourceSecurityError['code'],
                            'status' => Response::HTTP_UNAUTHORIZED,
                            'detail' => $resourceSecurityError['detail'],
                            'message' => $resourceSecurityError['message'],
                        ],
                    ],
                ],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        // When no valid Bearer token is present, the user never authenticated.
        // Backend API (Generated\Api\Backend\*) resources use "Unauthorized request." to match old Glue BAPI behavior.
        // Storefront and other resources use "Missing access token."
        if (!$hasValidBearerToken) {
            $isBackendResource = str_contains($resourceClass, '\Api\Backend\\');

            if (!$isBackendResource) {
                return $this->createJsonApiResponse(
                    [
                        'errors' => [
                            [
                                'code' => static::ERROR_CODE_MISSING_ACCESS_TOKEN,
                                'status' => Response::HTTP_FORBIDDEN,
                                'detail' => static::ERROR_DETAIL_MISSING_ACCESS_TOKEN,
                            ],
                        ],
                    ],
                    Response::HTTP_FORBIDDEN,
                );
            }
        }

        // Authenticated user denied by a resource-specific voter (e.g. CUSTOMER_OWNER).
        // For GET: hide resource existence with the provider's not-found error.
        // For write operations: return the resource's configured security error with 403.
        if ($resourceSecurityError !== null) {
            return $this->createAuthorizationDeniedResponse($request, $resourceClass, $resourceSecurityError, $extraProperties);
        }

        // Authorization header was present but user lacks required role/permission
        return $this->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'code' => static::ERROR_CODE_UNAUTHORIZED_REQUEST,
                        'status' => Response::HTTP_FORBIDDEN,
                        'detail' => static::ERROR_DETAIL_UNAUTHORIZED_REQUEST,
                        'message' => static::ERROR_DETAIL_UNAUTHORIZED_REQUEST,
                    ],
                ],
            ],
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Builds a domain-specific error response for an authenticated user who was denied
     * by a resource-level security voter.
     *
     * For GET requests on resources with securityGetStatusCode (e.g. 404), the response
     * hides resource existence by returning the provider's not-found error. For all other
     * operations, the resource's configured securityCode is returned with 403.
     *
     * @param array{code: string, detail: string, message: string}|array $resourceSecurityError
     * @param array<string, mixed> $extraProperties
     */
    protected function createAuthorizationDeniedResponse(
        Request $request,
        string $resourceClass,
        array $resourceSecurityError,
        array $extraProperties,
    ): JsonResponse {
        if ($request->getMethod() === 'GET') {
            $securityGetStatusCode = $extraProperties['securityGetStatusCode'] ?? null;

            if ($securityGetStatusCode !== null) {
                $notFoundError = $this->resolveProviderNotFoundError($resourceClass);

                if ($notFoundError !== null) {
                    return $this->createJsonApiResponse(
                        ['errors' => [$notFoundError]],
                        (int)$securityGetStatusCode,
                    );
                }
            }
        }

        return $this->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'code' => $resourceSecurityError['code'],
                        'status' => Response::HTTP_FORBIDDEN,
                        'detail' => $resourceSecurityError['detail'],
                        'message' => $resourceSecurityError['message'],
                    ],
                ],
            ],
            Response::HTTP_FORBIDDEN,
        );
    }

    /**
     * Reads all security-related extra properties from the resource's #[ApiResource] attribute
     * in a single reflection call, avoiding repeated attribute instantiation per request.
     *
     * @return array<string, mixed>
     */
    protected function resolveResourceExtraProperties(string $resourceClass): array
    {
        if ($resourceClass === '' || !class_exists($resourceClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($resourceClass);
            $attributes = $reflection->getAttributes(ApiResource::class);

            if ($attributes === []) {
                return [];
            }

            $apiResource = $attributes[0]->newInstance();
            $extraProperties = $apiResource->getExtraProperties() ?? [];

            $securityMessage = $apiResource->getSecurityMessage();
            if ($securityMessage !== null) {
                $extraProperties['securityMessage'] = $securityMessage;
            }

            return $extraProperties;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Resolves the domain-specific security error for an access-denied response.
     * Returns null when the resource has no custom securityCode configured.
     *
     * @param array<string, mixed> $extraProperties
     *
     * @return array{code: string, detail: string, message: string}|null
     */
    protected function resolveResourceSecurityError(array $extraProperties): ?array
    {
        $securityCode = $extraProperties['securityCode'] ?? null;

        if ($securityCode === null) {
            return null;
        }

        $securityMessage = (string)($extraProperties['securityMessage'] ?? static::ERROR_DETAIL_UNAUTHORIZED_REQUEST);

        return [
            'code' => (string)$securityCode,
            'detail' => $securityMessage,
            'message' => rtrim($securityMessage, '.'),
        ];
    }
}
