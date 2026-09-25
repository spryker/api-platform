<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use Codeception\Stub;
use Codeception\Test\TestCaseWrapper;
use Codeception\TestInterface;
use Generated\Shared\Transfer\CompanyUserTransfer;
use Generated\Shared\Transfer\CustomerTransfer;
use Generated\Shared\Transfer\OauthAccessTokenValidationResponseTransfer;
use Spryker\Client\Oauth\OauthClientInterface;
use Spryker\Glue\AuthRestApi\AuthRestApiDependencyProvider;
use Spryker\Glue\AuthRestApi\AuthRestApiFactory;
use Spryker\Glue\AuthRestApi\Dependency\Client\AuthRestApiToOauthClientBridge;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use SprykerTest\Shared\Customer\Helper\CustomerDataHelper;
use SprykerTest\Shared\Testify\Helper\DependencyHelperTrait;
use SprykerTest\Shared\Testify\Helper\LocatorHelperTrait;

/**
 * Centralized host-lane login for the API Platform test lanes, entered through
 * {@see actingAsCustomer()}. Enable it on the suite's actor in codeception.yml; the test class must
 * extend {@see AbstractApiTestCase} so the helper can reach the booted kernel.
 *
 * The OAuth client service is a single {@see SwappableOauthClient} proxy registered on first boot,
 * whose delegate this helper swaps per test method — so the identity flips without rebuilding the
 * kernel.
 */
class ApiLoginHelper extends Module
{
    use DependencyHelperTrait;
    use LocatorHelperTrait;

    protected const string ROLE_CUSTOMER = 'ROLE_CUSTOMER';

    /**
     * OAuth scope the storefront maps to a role via
     * {@see \Spryker\ApiPlatform\Security\ApiUserProvider::mapScopesToRoles()} (`ROLE_<SCOPE>`).
     */
    protected const string SCOPE_CUSTOMER = 'customer';

    /**
     * Roles this helper can grant, mapped to the OAuth scope the storefront turns into them.
     *
     * Only `ROLE_CUSTOMER` is needed today (the sole role Wishlists requires). This map is the
     * extension seam for future roles — add an entry (and a public `actingAs…()` method) when a
     * resource actually needs another role; do not add speculative entries.
     *
     * @var array<string, string>
     */
    protected const array ROLE_SCOPE_MAP = [
        self::ROLE_CUSTOMER => self::SCOPE_CUSTOMER,
    ];

    /**
     * Symfony DI service id of the storefront OAuth client. It is autowired into
     * {@see \Spryker\ApiPlatform\Security\OauthAuthenticator} (firewall) and
     * {@see \Spryker\ApiPlatform\EventSubscriber\IdentityRequestSubscriber} (claims → request).
     * A {@see SwappableOauthClient} is bound here so a test authenticates without a running
     * OAuth server or a signed JWT.
     */
    protected const string SERVICE_ID_OAUTH_CLIENT = OauthClientInterface::class;

    protected const string HEADER_AUTHORIZATION = 'Authorization';

    /**
     * The stubbed OAuth client ignores the token string (the identity comes from the stub), so
     * any non-empty bearer value is enough to make the firewall's authenticator engage.
     */
    protected const string BEARER_TOKEN = 'Bearer stubbed-oauth-test-token';

    /**
     * Never add a `uuid` claim: `OauthAuthenticator` would extract it as the sole user id and
     * collapse the identifier to a bare string, unlike a real customer token.
     */
    protected const string CLAIM_CUSTOMER_REFERENCE = 'customer_reference';

    protected const string CLAIM_ID_CUSTOMER = 'id_customer';

    /**
     * Carries the company user **uuid**, which
     * {@see \Spryker\Glue\CompanyUsersRestApi\Api\Storefront\EventSubscriber\CompanyUserIdentityRequestSubscriber}
     * resolves against `spy_company_user_storage` to reach the integer id — so a test authenticating
     * as a company user must publish `Entity.spy_company_user.create` as part of its arrange.
     */
    protected const string CLAIM_ID_COMPANY_USER = 'id_company_user';

    protected ?AbstractApiTestCase $testCase = null;

    protected ?string $companyUserUuid = null;

    /**
     * The one OAuth-client instance bound into the shared kernel; its delegate is swapped per
     * method (see the class-level "How the OAuth seam works" note).
     */
    protected ?SwappableOauthClient $oauthClient = null;

    public function _before(TestInterface $test): void
    {
        $testCase = $test instanceof TestCaseWrapper ? $test->getTestCase() : $test;

        if (!$testCase instanceof AbstractApiTestCase) {
            return;
        }

        $this->testCase = $testCase;
        $this->companyUserUuid = null;

        // Default every method to "not logged in": bind the proxy (so the OAuth service is always
        // resolvable — an anonymous secured request is a 403, not a container 500) with an
        // invalid-token delegate. actingAsCustomer() overrides the delegate for this method.
        $this->getOauthClient()->setDelegate($this->createInvalidOauthClientStub());
        $this->testCase->setService(static::SERVICE_ID_OAUTH_CLIENT, $this->getOauthClient());
        $this->bindLegacyGlueOauthClient();
    }

    /**
     * Points the legacy Glue OAuth seam at the same proxy as the Symfony one. A request is
     * validated twice, and the legacy half resolves its client through the Spryker locator, which
     * the container rebinding cannot reach.
     */
    protected function bindLegacyGlueOauthClient(): void
    {
        $this->getDependencyHelper()->setDependency(
            AuthRestApiDependencyProvider::CLIENT_OAUTH,
            new AuthRestApiToOauthClientBridge($this->getOauthClient()),
            AuthRestApiFactory::class,
        );
    }

    public function _after(TestInterface $test): void
    {
        // Leave the shared proxy in the anonymous state for the next method (the kernel is reused;
        // the instance is not).
        if ($this->oauthClient !== null) {
            $this->oauthClient->setDelegate($this->createInvalidOauthClientStub());
        }

        $this->companyUserUuid = null;
        $this->testCase = null;
    }

    /**
     * Makes the next request through the booted kernel be treated as this authenticated customer
     * with `ROLE_CUSTOMER`. Stubbing the backend client the resource provider calls stays the
     * test's own business. Pass no customer to authenticate as a faker-generated default.
     */
    public function actingAsCustomer(?CustomerTransfer $customer = null): void
    {
        $this->actingWithScopes([static::ROLE_SCOPE_MAP[static::ROLE_CUSTOMER]], $customer);
    }

    /**
     * Authenticates as {@see actingAsCustomer()} does, and additionally carries the company user the
     * storefront resolves the customer's company context from.
     *
     * A B2B resource is scoped by that context rather than by the customer alone — e.g.
     * {@see \Spryker\Zed\ShoppingList\Business\Model\ShoppingListReader::checkReadPermission()}
     * refuses outright when the company-user id is unset, so a plain customer sees a 404 for a list
     * it owns.
     */
    public function actingAsCompanyUser(CustomerTransfer $customer, CompanyUserTransfer $companyUser): void
    {
        $this->companyUserUuid = $companyUser->getUuidOrFail();

        $this->actingAsCustomer($customer);
    }

    /**
     * Persists a customer and authenticates as them - the two-step arrange every authenticated
     * Storefront suite otherwise repeats. {@see actingAsCustomer()} is the half that authenticates
     * a customer the caller already arranged.
     */
    public function arrangeAuthenticatedCustomer(): CustomerTransfer
    {
        $customerTransfer = $this->getCustomerDataHelper()->haveCustomer();
        $this->actingAsCustomer($customerTransfer);

        return $customerTransfer;
    }

    /**
     * Negative-auth seam: authenticates a valid token carrying an explicit scope list, so a test
     * can tell "authenticated but forbidden" from the anonymous `403`. Prefer
     * {@see actingAsCustomer()} for the happy case. This grants exactly what `$scopes` map to and
     * never touches {@see ROLE_SCOPE_MAP}.
     *
     * @param array<string> $scopes
     */
    public function actingWithScopes(array $scopes, ?CustomerTransfer $customer = null): void
    {
        $this->assertTestCaseAvailable();

        $customer ??= $this->getDefaultAuthenticatedCustomer();

        $claims = $this->buildCustomerClaims($customer);

        $this->getOauthClient()->setDelegate($this->createValidOauthClientStub($claims, $scopes));
        $this->testCase->setService(static::SERVICE_ID_OAUTH_CLIENT, $this->getOauthClient());

        $this->testCase->addDefaultRequestHeader(static::HEADER_AUTHORIZATION, static::BEARER_TOKEN);
    }

    /**
     * Sends the next request with a syntactically valid bearer whose token the stubbed OAuth client
     * reports invalid, so the firewall's authenticator engages and then fails — the
     * "expired/garbage token" case, which yields a `401` (as opposed to the anonymous `403`).
     */
    public function actingWithInvalidToken(): void
    {
        $this->assertTestCaseAvailable();

        // `_before()` already bound the invalid-token delegate; only the bearer header is needed to
        // make the firewall attempt (and then fail) authentication.
        $this->testCase->addDefaultRequestHeader(static::HEADER_AUTHORIZATION, static::BEARER_TOKEN);
    }

    /**
     * The default authenticated customer, built (non-persisting) by the core CustomerDataHelper —
     * the API test lanes carry no customer code of their own.
     */
    protected function getDefaultAuthenticatedCustomer(): CustomerTransfer
    {
        return $this->getCustomerDataHelper()->haveCustomerTransfer();
    }

    protected function getCustomerDataHelper(): CustomerDataHelper
    {
        /** @var \SprykerTest\Shared\Customer\Helper\CustomerDataHelper $customerDataHelper */
        $customerDataHelper = $this->getModule('\\' . CustomerDataHelper::class);

        return $customerDataHelper;
    }

    /**
     * Token validation is stubbed (there is no OAuth server here); token issuance is not, so a
     * resource whose job is to mint a token exercises the real grant.
     */
    protected function getOauthClient(): SwappableOauthClient
    {
        if ($this->oauthClient === null) {
            $this->oauthClient = new SwappableOauthClient(
                $this->createInvalidOauthClientStub(),
                fn (): OauthClientInterface => $this->getLocator()->oauth()->client(),
            );
        }

        return $this->oauthClient;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCustomerClaims(CustomerTransfer $customer): array
    {
        $customerReference = (string)$customer->getCustomerReference();

        $claims = [
            static::CLAIM_CUSTOMER_REFERENCE => $customerReference,
        ];

        if ($customer->getIdCustomer() !== null) {
            $claims[static::CLAIM_ID_CUSTOMER] = $customer->getIdCustomer();
        }

        if ($this->companyUserUuid !== null) {
            $claims[static::CLAIM_ID_COMPANY_USER] = $this->companyUserUuid;
        }

        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string> $scopes
     */
    protected function createValidOauthClientStub(array $claims, array $scopes): OauthClientInterface
    {
        $oauthUserId = json_encode($claims, JSON_THROW_ON_ERROR);

        /** @var \Spryker\Client\Oauth\OauthClientInterface $stub */
        $stub = Stub::makeEmpty(OauthClientInterface::class, [
            'validateOauthAccessToken' => static function () use ($oauthUserId, $scopes): OauthAccessTokenValidationResponseTransfer {
                return (new OauthAccessTokenValidationResponseTransfer())
                    ->setIsValid(true)
                    ->setOauthUserId($oauthUserId)
                    ->setOauthScopes($scopes);
            },
        ]);

        return $stub;
    }

    protected function createInvalidOauthClientStub(): OauthClientInterface
    {
        /** @var \Spryker\Client\Oauth\OauthClientInterface $stub */
        $stub = Stub::makeEmpty(OauthClientInterface::class, [
            'validateOauthAccessToken' => static function (): OauthAccessTokenValidationResponseTransfer {
                return (new OauthAccessTokenValidationResponseTransfer())->setIsValid(false);
            },
        ]);

        return $stub;
    }

    protected function assertTestCaseAvailable(): void
    {
        if ($this->testCase === null) {
            $this->fail(sprintf(
                'ApiLoginHelper requires the test class to extend %s.',
                AbstractApiTestCase::class,
            ));
        }
    }
}
