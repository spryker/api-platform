<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\EventSubscriber;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spryker\ApiPlatform\Error\JsonApiErrorResponseFactory;
use Spryker\ApiPlatform\Exception\GlueApiException;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\ResponseTransform\RelativeLinkTransform;
use Spryker\ApiPlatform\Security\AccessDeniedErrorResponseBuilder;
use Spryker\ApiPlatform\Validation\BoolValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\DenormalizationErrorMatcher;
use Spryker\ApiPlatform\Validation\NestedObjectValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\NumericValidationErrorAugmenter;
use Spryker\ApiPlatform\Validation\SynthesizedViolation;
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Spryker\ApiPlatform\Validation\ValidationConstraintReader;
use Spryker\ApiPlatform\Validation\ValidationErrorFormatNormalizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Intercepts GlueApiException and AccessDeniedException instances and converts them
 * into JSON:API formatted error responses with Glue-compatible `code` field included.
 *
 * The single entry point for Glue error handling on API Platform requests: it decides which
 * event and which case applies, and delegates building and augmenting the error to its collaborators.
 */
class GlueApiExceptionSubscriber implements EventSubscriberInterface
{
    use ValidationMessageTranslationTrait;

    public const string ERROR_CODE_UNAUTHORIZED_REQUEST = AccessDeniedErrorResponseBuilder::ERROR_CODE_UNAUTHORIZED_REQUEST;

    public const string ERROR_DETAIL_UNAUTHORIZED_REQUEST = AccessDeniedErrorResponseBuilder::ERROR_DETAIL_UNAUTHORIZED_REQUEST;

    public const string ERROR_DETAIL_BAD_REQUEST = 'Post data missing or invalid.';

    // The write methods whose body can carry a nested value object.
    protected const array NESTED_OBJECT_WRITE_METHODS = [Request::METHOD_POST, Request::METHOD_PATCH];

    protected const string ENGLISH_LOCALE = 'en';

    protected JsonApiErrorResponseFactory $errorResponseFactory;

    protected AccessDeniedErrorResponseBuilder $accessDeniedErrorResponseBuilder;

    protected DenormalizationErrorMatcher $denormalizationErrorMatcher;

    protected ValidationErrorFormatNormalizer $validationErrorFormatNormalizer;

    protected NumericValidationErrorAugmenter $numericValidationErrorAugmenter;

    protected BoolValidationErrorAugmenter $boolValidationErrorAugmenter;

    protected RelativeLinkTransform $relativeLinkTransform;

    public function __construct(
        protected TranslatorInterface $translator,
        protected ResourceMetadataCollectionFactoryInterface $resourceMetadataCollectionFactory,
        protected ValidationConstraintReader $constraintReader,
        protected NestedObjectValidationErrorAugmenter $nestedObjectAugmenter,
        protected bool $debug,
        protected LoggerInterface $logger = new NullLogger(),
        protected bool $isMethodNotAllowedStatusEnabled = true,
        protected ProviderNotFoundErrorResolver $providerNotFoundErrorResolver = new ProviderNotFoundErrorResolver(),
        ?JsonApiErrorResponseFactory $errorResponseFactory = null,
        ?AccessDeniedErrorResponseBuilder $accessDeniedErrorResponseBuilder = null,
        ?DenormalizationErrorMatcher $denormalizationErrorMatcher = null,
        ?ValidationErrorFormatNormalizer $validationErrorFormatNormalizer = null,
        ?NumericValidationErrorAugmenter $numericValidationErrorAugmenter = null,
        ?BoolValidationErrorAugmenter $boolValidationErrorAugmenter = null,
        ?RelativeLinkTransform $relativeLinkTransform = null,
    ) {
        $this->errorResponseFactory = $errorResponseFactory ?? new JsonApiErrorResponseFactory($providerNotFoundErrorResolver);
        $this->accessDeniedErrorResponseBuilder = $accessDeniedErrorResponseBuilder
            ?? new AccessDeniedErrorResponseBuilder($translator, $this->errorResponseFactory, $providerNotFoundErrorResolver);
        $this->denormalizationErrorMatcher = $denormalizationErrorMatcher ?? new DenormalizationErrorMatcher($translator);
        $this->validationErrorFormatNormalizer = $validationErrorFormatNormalizer ?? new ValidationErrorFormatNormalizer($translator);
        $this->numericValidationErrorAugmenter = $numericValidationErrorAugmenter
            ?? new NumericValidationErrorAugmenter($constraintReader, $translator);
        $this->boolValidationErrorAugmenter = $boolValidationErrorAugmenter ?? new BoolValidationErrorAugmenter($translator, $constraintReader);
        $this->relativeLinkTransform = $relativeLinkTransform ?? new RelativeLinkTransform();
    }

    /**
     * @return array<string, array<int, array{string, int}>|array{string, int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequestSetValidationLocale', 9],
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

        if (!$request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            return;
        }

        $content = $request->getContent();

        if ($content === '' || $content === '[]' || $content === '{}' || $content === 'null') {
            if ($this->isDeserializationDisabled($request)) {
                return;
            }

            $event->setResponse($this->errorResponseFactory->createBadRequestResponse(
                $this->translateValidationMessage(static::ERROR_DETAIL_BAD_REQUEST),
            ));
        }
    }

    protected function isDeserializationDisabled(Request $request): bool
    {
        $operation = $request->attributes->get(RequestAttribute::API_OPERATION);

        if ($operation === null) {
            $resourceClass = $request->attributes->get(RequestAttribute::API_RESOURCE_CLASS);
            $operationName = $request->attributes->get(RequestAttribute::API_OPERATION_NAME);

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
        $request = $event->getRequest();

        if ($exception instanceof GlueApiException) {
            $event->setResponse($this->errorResponseFactory->createGlueApiErrorResponse($exception));

            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $event->setResponse($this->accessDeniedErrorResponseBuilder->createAccessDeniedResponse($request));

            return;
        }

        // Convert API Platform deserialization/validation 400 errors to 422
        // for backward compatibility with the old REST API behavior.
        if ($exception instanceof BadRequestHttpException && $request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            $event->setResponse($this->errorResponseFactory->createValidationErrorResponse([
                $this->denormalizationErrorMatcher->transformDenormalizationMessage($exception->getMessage()),
            ]));

            return;
        }

        // Without this branch a property that cannot take the submitted value renders a 500.
        // Legacy Glue returned 422 / code 901 with "<property> => This value should be of type numeric."
        if ($request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            $propertyTypeError = $this->denormalizationErrorMatcher->match($exception);

            if ($propertyTypeError !== null) {
                $event->setResponse($this->errorResponseFactory->createValidationErrorResponse([$propertyTypeError]));

                return;
            }
        }

        if ($exception instanceof MethodNotAllowedHttpException && !$request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            $event->setResponse($this->isMethodNotAllowedStatusEnabled
                ? $this->errorResponseFactory->createMethodNotAllowedResponse($exception, $request)
                : $this->errorResponseFactory->createHttpExceptionResponse(new NotFoundHttpException(), $request));

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
            $isNonApiPlatformFallback = $request->attributes->get(RequestAttribute::API_PLATFORM_REQUEST) === true
                && !$request->attributes->has(RequestAttribute::API_RESOURCE_CLASS);

            if (!$isNonApiPlatformFallback) {
                $event->setResponse($this->errorResponseFactory->createHttpExceptionResponse($exception, $request));
            }
        }
    }

    /**
     * Last-resort guard for throwables that no other subscriber turned into a response.
     *
     * Runs after the application exception subscribers (priority 256 above,
     * OAuthExceptionSubscriber at 10) but before API Platform's own exception
     * listener (-96). API Platform's listener only renders requests that carry its
     * routing attributes, and Symfony's ErrorListener is not registered, so a
     * throwable raised before the router resolved an operation (kernel.request
     * subscribers, authenticators, the router itself) would otherwise leave the
     * kernel unhandled and `ApiApplicationProxy` would answer with the Glue 404.
     *
     * Every request routed to the API Platform kernel (`api-platform-request`) is
     * covered. In production ($debug = false) the throwable is replaced with a
     * generic 500 so traces never reach the client. In development ($debug = true)
     * a resolved operation is left to API Platform's debug error renderer, and a
     * request without one gets a JSON:API 500 that carries the exception details.
     *
     * HTTP exceptions are intentionally skipped: direct ones are already handled
     * at priority 256, the OAuthExceptionSubscriber-converted one keeps its status
     * code through API Platform's renderer, and a not-found on a fallback request
     * must propagate so the proxy keeps the original Glue response.
     */
    public function onKernelExceptionLastResort(ExceptionEvent $event): void
    {
        if ($event->getResponse() !== null) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isApiPlatformRequest($request)) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof HttpExceptionInterface) {
            return;
        }

        $this->logUncaughtThrowable($throwable, $request);

        if (!$this->debug) {
            $event->setResponse($this->errorResponseFactory->createInternalServerErrorResponse());

            return;
        }

        if ($request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            return;
        }

        $event->setResponse($this->errorResponseFactory->createDebugInternalServerErrorResponse($throwable));
    }

    protected function isApiPlatformRequest(Request $request): bool
    {
        return $request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)
            || $request->attributes->get(RequestAttribute::API_PLATFORM_REQUEST) === true;
    }

    protected function logUncaughtThrowable(Throwable $throwable, Request $request): void
    {
        $this->logger->error(
            sprintf(
                'Uncaught %s on API Platform request "%s %s": %s in %s:%d',
                $throwable::class,
                $request->getMethod(),
                $request->getPathInfo(),
                $throwable->getMessage(),
                $throwable->getFile(),
                $throwable->getLine(),
            ),
            ['exception' => $throwable],
        );
    }

    /**
     * Converts API Platform's 400 deserialization errors to 422 with Spryker error code 901,
     * preserving backward compatibility with the old REST API validation error format.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        $this->relativeLinkTransform->transform($response, $request);

        if (!$request->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            return;
        }

        $resourceClass = (string)$request->attributes->get(RequestAttribute::API_RESOURCE_CLASS, '');

        // Enrich generic 404 responses from API Platform with domain-specific error messages.
        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            $this->enrichNotFoundResponse($response, $resourceClass);
        }

        // Augment 422 validation responses with missing Type/GreaterThan errors for empty-string numeric fields.
        // API Platform converts empty strings to null for typed properties (e.g. ?int), which causes Type and
        // comparison constraints to pass. The old REST API validated raw strings, so all errors were returned.
        if ($response->getStatusCode() === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $this->augmentValidationErrors($response, $request, $resourceClass);
        }

        // Augment nested value-object validation: re-check coerced bool leaves, and relabel or
        // synthesize "This field is missing." for present-but-empty nested objects.
        // Runs outside the 422 guard because a present-but-empty required object (whose leaf
        // constraints allow null) otherwise yields no errors and a 200 — this pass forces 422.
        // An update needs it for the same reason: a write-only operation denormalizes onto a
        // pre-populated instance, so a nested `?bool` leaf submitted as a non-boolean is coerced by
        // the generated setter and answers 200 unless the raw body is re-checked here.
        if ($resourceClass !== '' && in_array($request->getMethod(), static::NESTED_OBJECT_WRITE_METHODS, true)) {
            $this->augmentValidationErrorsForNestedObjects($event, $request, $resourceClass);
        }

        // The response as it arrived, not one the nested pass may have replaced.
        if ($response->getStatusCode() === Response::HTTP_BAD_REQUEST) {
            $this->convertDenormalizationErrors($event, $response);
        }
    }

    protected function enrichNotFoundResponse(Response $response, string $resourceClass): void
    {
        // Do not overwrite responses that already have domain-specific error codes
        // (e.g., from GlueApiException). Only enrich generic API Platform 404 responses.
        $data = $this->decodeErrorResponse($response);

        if ($data !== null && isset($data['errors'][0]['code']) && $data['errors'][0]['code'] !== '404') {
            return;
        }

        $notFoundError = $this->providerNotFoundErrorResolver->resolveByResourceClass($resourceClass);

        if ($notFoundError === null) {
            return;
        }

        $response->setContent((string)json_encode(
            ['errors' => [$notFoundError]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    protected function augmentValidationErrors(Response $response, Request $request, string $resourceClass): void
    {
        $data = $this->decodeErrorResponse($response);

        if ($data !== null && isset($data['errors'])) {
            $normalizedErrors = $this->validationErrorFormatNormalizer->normalize($data['errors'], $this->resolveSubmittedFields($request));

            if ($normalizedErrors !== null) {
                $response->setContent((string)json_encode(['errors' => $normalizedErrors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        if ($resourceClass === '') {
            return;
        }

        $rawAttributes = $this->extractRawAttributes($request);
        $groups = $this->getActiveValidationGroups($request);

        $this->rewriteErrors($response, fn (array $errors): array => $this->numericValidationErrorAugmenter
            ->augmentEmptyStringValues($resourceClass, $groups, $errors), $request);
        $this->rewriteErrors($response, fn (array $errors): array => $this->numericValidationErrorAugmenter
            ->augmentStringNumericValues($resourceClass, $rawAttributes, $groups, $errors), $request);

        if ($request->getMethod() === Request::METHOD_POST) {
            $this->rewriteErrors($response, fn (array $errors): array => $this->boolValidationErrorAugmenter
                ->augment($resourceClass, $rawAttributes, $errors, $groups), $request);
        }
    }

    /**
     * Only deserialization errors (a detail mentioning "denormalize" or a syntax error) are converted.
     */
    protected function convertDenormalizationErrors(ResponseEvent $event, Response $response): void
    {
        $data = $this->decodeErrorResponse($response);

        if ($data === null || !isset($data['errors'])) {
            return;
        }

        $detail = $data['errors'][0]['detail'] ?? '';

        if (!str_contains($detail, 'denormalize') && !str_contains($detail, 'Syntax error')) {
            return;
        }

        $details = [];

        foreach ($data['errors'] as $error) {
            $details[] = $this->denormalizationErrorMatcher->transformDenormalizationMessage(
                $error['detail'] ?? $error['title'] ?? 'Validation error.',
            );
        }

        $event->setResponse($this->errorResponseFactory->createValidationErrorResponse($details));
    }

    /**
     * Delegates present-but-empty nested value-object augmentation to the augmenter, which is a pure
     * transformer over the decoded error array. This subscriber owns all request/response I/O: it
     * resolves the request-derived inputs, hands them to the augmenter, and rebuilds the response
     * only when the augmenter reports a change. Synthesizing missing-field errors on an
     * otherwise-passing request requires promoting the status to 422, so a modified result always
     * yields a fresh 422 response with consistent headers/status.
     */
    protected function augmentValidationErrorsForNestedObjects(ResponseEvent $event, Request $request, string $resourceClass): void
    {
        $data = $this->decodeErrorResponse($event->getResponse());
        $errors = is_array($data) && isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : [];

        $result = $this->nestedObjectAugmenter->augment(
            $resourceClass,
            $this->extractRawAttributes($request),
            $this->getActiveValidationGroups($request),
            $errors,
        );

        if (!$result->modified) {
            return;
        }

        $event->setResponse($this->errorResponseFactory->createJsonApiResponse(
            ['errors' => $this->takeSynthesizedViolations($request, $result->errors)],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        ));
    }

    /**
     * Hands the response's decoded errors to an augmenter and writes the body back only when it changed them.
     *
     * @param callable(array<int, array<string, mixed>>): array<int, array<string, mixed>> $augment
     */
    protected function rewriteErrors(Response $response, callable $augment, ?Request $request = null): void
    {
        $data = $this->decodeErrorResponse($response);

        if ($data === null || !isset($data['errors'])) {
            return;
        }

        $errors = $augment($data['errors']);

        if ($errors === $data['errors']) {
            return;
        }

        $data['errors'] = $this->takeSynthesizedViolations($request, $errors);
        $response->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Moves the synthesized violations off the errors and onto the request, so they never reach the
     * wire and the contract coverage can still read what each synthesized error stands for. Without
     * a request they are only dropped.
     *
     * @param array<int, array<string, mixed>> $errors
     *
     * @return array<int, array<string, mixed>>
     */
    protected function takeSynthesizedViolations(?Request $request, array $errors): array
    {
        $violations = $request?->attributes->get(RequestAttribute::SYNTHESIZED_VIOLATIONS, []) ?? [];

        foreach ($errors as $index => $error) {
            if (!isset($error[SynthesizedViolation::ERROR_KEY])) {
                continue;
            }

            array_push($violations, ...$error[SynthesizedViolation::ERROR_KEY]);
            unset($errors[$index][SynthesizedViolation::ERROR_KEY]);
        }

        $request?->attributes->set(RequestAttribute::SYNTHESIZED_VIOLATIONS, $violations);

        return $errors;
    }

    /**
     * Puts the translator into the locale the request was resolved to, for the whole validation pass.
     *
     * A constraint violation is interpolated into its final text by the validator itself, at the
     * moment it is created, using whatever locale the translator carries then — so this is the only
     * point at which the language of a validation message can still be chosen. Every renderer
     * downstream receives `$violation->getMessage()` already in that language.
     *
     * English remains the answer when nothing resolved a locale for the request, which is what
     * callers sending no `Accept-Language` have always received.
     *
     * Scoped to API Platform routes: legacy Glue endpoints resolve their own locale after validation
     * runs and must keep doing so.
     */
    public function onKernelRequestSetValidationLocale(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$event->getRequest()->attributes->has(RequestAttribute::API_RESOURCE_CLASS)) {
            return;
        }

        if (!$this->translator instanceof LocaleAwareInterface) {
            return;
        }

        $this->translator->setLocale(
            $event->getRequest()->attributes->get(RequestAttribute::LOCALE) ?? static::ENGLISH_LOCALE,
        );
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
     * @return array<string>
     */
    protected function getActiveValidationGroups(Request $request): array
    {
        $operation = $request->attributes->get(RequestAttribute::API_OPERATION);

        if (!$operation instanceof Operation) {
            return [];
        }

        return $operation->getValidationContext()['groups'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeErrorResponse(Response $response): ?array
    {
        $content = $response->getContent();

        if ($content === false || $content === '') {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
