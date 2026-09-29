<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security;

use ApiPlatform\Metadata\ApiResource;
use ReflectionClass;
use Spryker\ApiPlatform\Error\JsonApiErrorResponseFactory;
use Spryker\ApiPlatform\OpenApi\ErrorResponse\ProviderNotFoundErrorResolver;
use Spryker\ApiPlatform\Request\RequestAttribute;
use Spryker\ApiPlatform\Validation\Trait\ValidationMessageTranslationTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Answers an access denial with the legacy Glue error its resource and the request's credentials call for.
 */
class AccessDeniedErrorResponseBuilder
{
    use ValidationMessageTranslationTrait;

    public const string ERROR_CODE_UNAUTHORIZED_REQUEST = '802';

    public const string ERROR_DETAIL_UNAUTHORIZED_REQUEST = 'Unauthorized request.';

    protected const string ERROR_CODE_MISSING_ACCESS_TOKEN = '002';

    protected const string ERROR_DETAIL_MISSING_ACCESS_TOKEN = 'Missing access token.';

    protected const string AUTHORIZATION_HEADER = 'Authorization';

    protected const string ANONYMOUS_CUSTOMER_HEADER = 'X-Anonymous-Customer-Unique-Id';

    protected const string ERROR_CODE_CHECKOUT_AUTH_REQUIRED = '1105';

    protected const string ERROR_DETAIL_CHECKOUT_AUTH_REQUIRED = 'One of Authorization or X-Anonymous-Customer-Unique-Id headers is required.';

    public function __construct(
        protected TranslatorInterface $translator,
        protected JsonApiErrorResponseFactory $errorResponseFactory,
        protected ProviderNotFoundErrorResolver $providerNotFoundErrorResolver,
    ) {
    }

    public function createAccessDeniedResponse(Request $request): JsonResponse
    {
        $authorizationValue = (string)$request->headers->get(static::AUTHORIZATION_HEADER, '');
        $hasValidBearerToken = str_starts_with($authorizationValue, 'Bearer ') && strlen($authorizationValue) > 7;
        $hasAnonymousCustomerHeader = $request->headers->has(static::ANONYMOUS_CUSTOMER_HEADER);

        $resourceClass = (string)$request->attributes->get(RequestAttribute::API_RESOURCE_CLASS, '');
        $extraProperties = $this->resolveResourceExtraProperties($resourceClass);

        // Resources that accept either bearer or anonymous customer auth (e.g. checkout)
        // return 400 with a dedicated code when neither header is present — UNLESS the access denial
        // was raised by the CustomerAccess voter (b2b projects restricting `order-place-submit`/`price`
        // content types). In that case the legacy 403/002 "Missing access token." response takes
        // precedence so customer-access protection keeps behaving the way Glue REST did before the
        // API Platform migration. The flag is set in
        // {@see \Spryker\Glue\CustomerAccessRestApi\Api\Storefront\Security\CustomerAccessVoter}.
        $isCustomerAccessDenied = (bool)$request->attributes->get(RequestAttribute::CUSTOMER_ACCESS_DENIED, false);

        if (!$hasValidBearerToken && !$hasAnonymousCustomerHeader && ($extraProperties['securityAnonymousAuthRequired'] ?? false) && !$isCustomerAccessDenied) {
            return $this->errorResponseFactory->createJsonApiResponse(
                [
                    'errors' => [
                        [
                            'code' => static::ERROR_CODE_CHECKOUT_AUTH_REQUIRED,
                            'status' => Response::HTTP_BAD_REQUEST,
                            'detail' => $this->translateValidationMessage(static::ERROR_DETAIL_CHECKOUT_AUTH_REQUIRED),
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
            return $this->errorResponseFactory->createJsonApiResponse(
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
                return $this->errorResponseFactory->createJsonApiResponse(
                    [
                        'errors' => [
                            [
                                'code' => static::ERROR_CODE_MISSING_ACCESS_TOKEN,
                                'status' => Response::HTTP_FORBIDDEN,
                                'detail' => $this->translateValidationMessage(static::ERROR_DETAIL_MISSING_ACCESS_TOKEN),
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
        return $this->errorResponseFactory->createJsonApiResponse(
            [
                'errors' => [
                    [
                        'code' => static::ERROR_CODE_UNAUTHORIZED_REQUEST,
                        'status' => Response::HTTP_FORBIDDEN,
                        'detail' => $this->translateValidationMessage(static::ERROR_DETAIL_UNAUTHORIZED_REQUEST),
                        'message' => $this->translateValidationMessage(static::ERROR_DETAIL_UNAUTHORIZED_REQUEST),
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
     * @param array<string, mixed> $resourceSecurityError
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
                $notFoundError = $this->providerNotFoundErrorResolver->resolveByResourceClass($resourceClass);

                if ($notFoundError !== null) {
                    return $this->errorResponseFactory->createJsonApiResponse(
                        ['errors' => [$notFoundError]],
                        (int)$securityGetStatusCode,
                    );
                }
            }
        }

        return $this->errorResponseFactory->createJsonApiResponse(
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

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }
}
