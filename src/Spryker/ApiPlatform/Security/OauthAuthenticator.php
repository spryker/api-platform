<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security;

use Generated\Shared\Transfer\AuditLoggerConfigCriteriaTransfer;
use Generated\Shared\Transfer\OauthAccessTokenValidationRequestTransfer;
use Generated\Shared\Transfer\OauthAccessTokenValidationResponseTransfer;
use JsonException;
use Spryker\Client\Oauth\OauthClientInterface;
use Spryker\Shared\Log\AuditLoggerTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates API Platform requests using Spryker's OAuth JWT validation.
 *
 * Extracts the Bearer token from the Authorization header, validates it
 * via OauthClient (local JWT validation, no Zed call), and maps the
 * validated claims to an ApiUser with appropriate roles.
 */
class OauthAuthenticator extends AbstractAuthenticator
{
    use AuditLoggerTrait;

    protected const string AUTHORIZATION_HEADER = 'Authorization';

    protected const string BEARER_PREFIX = 'Bearer ';

    protected const string TOKEN_TYPE_BEARER = 'Bearer';

    protected const string CONTENT_TYPE_JSON_API = 'application/vnd.api+json';

    protected const string ERROR_TITLE_UNAUTHORIZED = 'Unauthorized';

    /**
     * @uses \Spryker\Glue\AuthRestApi\AuthRestApiConfig::RESPONSE_DETAIL_INVALID_ACCESS_TOKEN
     */
    protected const string ERROR_DETAIL_INVALID_TOKEN = 'Invalid access token.';

    /**
     * @uses \Spryker\Glue\AuthRestApi\AuthRestApiConfig::RESPONSE_DETAIL_MISSING_ACCESS_TOKEN
     */
    protected const string ERROR_DETAIL_MISSING_OR_INVALID_HEADER = 'Missing or invalid Authorization header';

    /**
     * @uses \Spryker\Glue\AuthRestApi\AuthRestApiConfig::RESPONSE_CODE_ACCESS_CODE_INVALID
     */
    protected const string ERROR_CODE_UNAUTHORIZED = '001';

    // Key used in the JWT sub claim to identify the user
    protected const string OAUTH_USER_DATA_KEY = 'uuid';

    /**
     * @uses \Spryker\Shared\Log\LogConfig::AUDIT_LOGGER_CHANNEL_NAME_SECURITY
     */
    protected const string AUDIT_LOGGER_CHANNEL_NAME_SECURITY = 'security';

    /**
     * @uses \Spryker\Shared\Log\Handler\TagFilterBufferedStreamHandler::RECORD_KEY_CONTEXT_TAGS
     */
    protected const string AUDIT_LOGGER_RECORD_KEY_CONTEXT_TAGS = 'tags';

    public function __construct(
        protected readonly OauthClientInterface $oauthClient,
        protected readonly ApiUserProvider $apiUserProvider,
    ) {
    }

    protected const string PATH_ACCESS_TOKENS = '/access-tokens';

    protected const string PATH_REFRESH_TOKENS = '/refresh-tokens';

    public function supports(Request $request): ?bool
    {
        $authorizationHeader = $request->headers->get(static::AUTHORIZATION_HEADER);

        if ($authorizationHeader === null || $authorizationHeader === '') {
            return false;
        }

        // Token endpoints handle their own credential validation — skip Bearer token auth
        $path = $request->getPathInfo();

        if ($path === static::PATH_ACCESS_TOKENS || $path === static::PATH_REFRESH_TOKENS) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        $validationResponse = $this->validateToken($token);

        if (!$validationResponse->getIsValid()) {
            throw new AuthenticationException(static::ERROR_DETAIL_INVALID_TOKEN);
        }

        $userIdentifier = $this->buildUserIdentifier($validationResponse);

        return new SelfValidatingPassport(
            new UserBadge($userIdentifier, $this->apiUserProvider->loadUserByIdentifier(...)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Allow the request to continue to the API Platform resource
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->getAuditLogger(
            (new AuditLoggerConfigCriteriaTransfer())->setChannelName(static::AUDIT_LOGGER_CHANNEL_NAME_SECURITY),
        )->info('Failed Login', [
            static::AUDIT_LOGGER_RECORD_KEY_CONTEXT_TAGS => ['failed_login'],
        ]);

        return new JsonResponse(
            [
                'errors' => [
                    [
                        'code' => static::ERROR_CODE_UNAUTHORIZED,
                        'status' => Response::HTTP_UNAUTHORIZED,
                        'title' => static::ERROR_TITLE_UNAUTHORIZED,
                        'detail' => static::ERROR_DETAIL_INVALID_TOKEN,
                    ],
                ],
            ],
            Response::HTTP_UNAUTHORIZED,
            ['Content-Type' => static::CONTENT_TYPE_JSON_API],
        );
    }

    protected function extractToken(Request $request): string
    {
        $authorizationHeader = $request->headers->get(static::AUTHORIZATION_HEADER);

        if ($authorizationHeader === null || !str_starts_with($authorizationHeader, static::BEARER_PREFIX)) {
            throw new AuthenticationException(static::ERROR_DETAIL_MISSING_OR_INVALID_HEADER);
        }

        return substr($authorizationHeader, strlen(static::BEARER_PREFIX));
    }

    protected function validateToken(string $accessToken): OauthAccessTokenValidationResponseTransfer
    {
        $validationRequest = new OauthAccessTokenValidationRequestTransfer();
        $validationRequest->setAccessToken($accessToken);
        $validationRequest->setType(static::TOKEN_TYPE_BEARER);

        return $this->oauthClient->validateOauthAccessToken($validationRequest);
    }

    protected function buildUserIdentifier(OauthAccessTokenValidationResponseTransfer $validationResponse): string
    {
        $claims = [
            ApiUser::CLAIM_USER_ID => $this->resolveUserId($validationResponse),
            ApiUser::CLAIM_OAUTH_CLIENT_ID => $validationResponse->getOauthClientId(),
            ApiUser::CLAIM_OAUTH_ACCESS_TOKEN_ID => $validationResponse->getOauthAccessTokenId(),
            ApiUser::CLAIM_OAUTH_SCOPES => $validationResponse->getOauthScopes(),
        ];

        return json_encode($claims, JSON_THROW_ON_ERROR);
    }

    /**
     * The OAuth user ID is a JSON-encoded string containing user data (e.g. {"id_user": 123}).
     * This method extracts the actual user identifier from the JSON payload.
     */
    protected function resolveUserId(OauthAccessTokenValidationResponseTransfer $validationResponse): string
    {
        $oauthUserId = $validationResponse->getOauthUserId();

        if ($oauthUserId === null) {
            return '';
        }

        try {
            $userData = json_decode($oauthUserId, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $oauthUserId;
        }

        if (!is_array($userData) || !isset($userData[static::OAUTH_USER_DATA_KEY])) {
            return $oauthUserId;
        }

        return (string)$userData[static::OAUTH_USER_DATA_KEY];
    }
}
