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
use Generated\Shared\Transfer\OauthAccessTokenValidationResponseTransfer;
use Generated\Shared\Transfer\UserTransfer;
use Spryker\Client\Oauth\OauthClientInterface;
use Spryker\Glue\AuthRestApi\AuthRestApiDependencyProvider;
use Spryker\Glue\AuthRestApi\AuthRestApiFactory;
use Spryker\Glue\AuthRestApi\Dependency\Client\AuthRestApiToOauthClientBridge;
use Spryker\Glue\KernelFeature\Security\AclAccessCheckerInterface;
use Spryker\Zed\Oauth\Business\OauthFacadeInterface;
use SprykerTest\ApiPlatform\Test\AbstractApiTestCase;
use SprykerTest\Shared\Testify\Helper\DependencyHelperTrait;
use SprykerTest\Shared\Testify\Helper\LocatorHelperTrait;
use SprykerTest\Shared\User\Helper\UserDataHelper;

/**
 * Centralized operator login for the Backend API test lanes. Enable it in a suite's
 * codeception.yml alongside {@see \SprykerTest\Shared\User\Helper\UserDataHelper}, which it takes
 * its default operator from; the test class must extend {@see AbstractApiTestCase} so the helper
 * can reach the booted kernel.
 *
 * A Backend request that targets a resource declaring a non-empty `security` expression is checked
 * by service graphs that do not share a client, so stubbing only some of them yields a confusing
 * pass-then-401:
 *
 * 1. {@see \Spryker\Glue\KernelFeature\Security\Listener\AuthenticationRequestListener} on
 *    `kernel.request`, which runs a validator chain:
 *    {@see \Spryker\Glue\KernelFeature\Security\Validator\BearerTokenValidator} introspects the
 *    bearer through the **Zed** {@see OauthFacadeInterface} (a 401 before the resource is reached,
 *    and the `id_user` it extracts feeds the next validator), then
 *    {@see \Spryker\Glue\KernelFeature\Security\Validator\AclValidator} asks
 *    {@see AclAccessCheckerInterface} whether that operator may touch this resource short name (403).
 *    The Storefront has no equivalent of this ACL step.
 * 2. The Symfony firewall's {@see \Spryker\ApiPlatform\Security\OauthAuthenticator}, which
 *    introspects through the injectable {@see OauthClientInterface} and turns OAuth **scopes** into
 *    roles via {@see \Spryker\ApiPlatform\Security\ApiUserProvider::mapScopesToRoles()}
 *    (`ROLE_<SCOPE>`), which is what satisfies `is_granted('ROLE_BACK_OFFICE_USER')`.
 * 3. The legacy Glue OAuth validator, which resolves its client through the Spryker locator rather
 *    than the Symfony container — see {@see bindLegacyGlueOauthClient()}.
 *
 * Under `bootOnce` + `reuseApplicationContainer` the firewall authenticator, the identity
 * subscriber and the validator chain capture their collaborators on the first boot, so a later
 * `setService()` for the same id cannot replace their reference. Each stub here therefore closes
 * over `$this` and reads the helper's own fields — the bound instance never changes while its
 * answers do, per method.
 */
class BackendApiLoginHelper extends Module
{
    use DependencyHelperTrait;
    use LocatorHelperTrait;

    protected const string SCOPE_USER = 'user';

    /**
     * @uses \Spryker\Zed\OauthUserConnector\OauthUserConnectorConfig::SCOPE_BACK_OFFICE_USER
     */
    protected const string SCOPE_BACK_OFFICE_USER = 'back-office-user';

    /**
     * @uses \Spryker\Zed\OauthMerchantUser\OauthMerchantUserConfig::SCOPE_MERCHANT_USER
     */
    protected const string SCOPE_MERCHANT_USER = 'merchant-user';

    protected const string HEADER_AUTHORIZATION = 'Authorization';

    protected const string BEARER_TOKEN = 'Bearer stubbed-backend-oauth-test-token';

    protected const string CLAIM_ID_USER = 'id_user';

    protected ?AbstractApiTestCase $testCase = null;

    protected bool $isTokenValid = false;

    protected bool $hasAclAccess = true;

    /**
     * @var array<string>
     */
    protected array $oauthScopes = [];

    protected ?int $idUser = null;

    public function _before(TestInterface $test): void
    {
        $testCase = $test instanceof TestCaseWrapper ? $test->getTestCase() : $test;

        if (!$testCase instanceof AbstractApiTestCase) {
            return;
        }

        $this->testCase = $testCase;

        $this->isTokenValid = false;
        $this->hasAclAccess = true;
        $this->oauthScopes = [];
        $this->idUser = null;

        $this->bindSecurityServices();
    }

    public function _after(TestInterface $test): void
    {
        $this->isTokenValid = false;
        $this->hasAclAccess = true;
        $this->oauthScopes = [];
        $this->idUser = null;
        $this->testCase = null;
    }

    public function actingAsUser(?UserTransfer $userTransfer = null): void
    {
        $this->actingWithScopes([static::SCOPE_USER, static::SCOPE_BACK_OFFICE_USER], $userTransfer);
    }

    /**
     * The merchant behind the token is resolved in Zed from the current user, not from a claim, so
     * the acting user needs a `spy_merchant_user` row for the request to reach anything
     * merchant-scoped. The merchant does not have to be approved.
     */
    public function actingAsMerchantUser(?UserTransfer $userTransfer = null): void
    {
        $this->actingWithScopes([static::SCOPE_USER, static::SCOPE_MERCHANT_USER], $userTransfer);
    }

    public function actingAsUserWithoutAclAccess(?UserTransfer $userTransfer = null): void
    {
        $this->actingAsUser($userTransfer);

        $this->hasAclAccess = false;
    }

    /**
     * @param array<string> $scopes
     */
    public function actingWithScopes(array $scopes, ?UserTransfer $userTransfer = null): void
    {
        $this->assertTestCaseAvailable();

        $userTransfer ??= $this->getDefaultActingUser();

        $this->idUser = $userTransfer->getIdUserOrFail();
        $this->oauthScopes = $scopes;
        $this->isTokenValid = true;

        $this->bindSecurityServices();

        $this->testCase->addDefaultRequestHeader(static::HEADER_AUTHORIZATION, static::BEARER_TOKEN);
    }

    public function actingWithInvalidToken(): void
    {
        $this->assertTestCaseAvailable();

        $this->isTokenValid = false;
        $this->hasAclAccess = true;
        $this->oauthScopes = [];
        $this->idUser = null;

        $this->bindSecurityServices();

        $this->testCase->addDefaultRequestHeader(static::HEADER_AUTHORIZATION, static::BEARER_TOKEN);
    }

    protected function getDefaultActingUser(): UserTransfer
    {
        /** @var \SprykerTest\Shared\User\Helper\UserDataHelper $userDataHelper */
        $userDataHelper = $this->getModule('\\' . UserDataHelper::class);

        return $userDataHelper->haveUser();
    }

    protected function bindSecurityServices(): void
    {
        if ($this->testCase === null) {
            return;
        }

        $this->testCase->setService(OauthClientInterface::class, $this->createOauthClientStub());
        $this->testCase->setService(OauthFacadeInterface::class, $this->createOauthFacadeStub());
        $this->testCase->setService(AclAccessCheckerInterface::class, $this->createAclAccessCheckerStub());

        $this->bindLegacyGlueOauthClient();
    }

    protected function bindLegacyGlueOauthClient(): void
    {
        $this->getDependencyHelper()->setDependency(
            AuthRestApiDependencyProvider::CLIENT_OAUTH,
            new AuthRestApiToOauthClientBridge($this->createOauthClientStub()),
            AuthRestApiFactory::class,
        );
    }

    protected function createOauthClientStub(): OauthClientInterface
    {
        /** @var \Spryker\Client\Oauth\OauthClientInterface $stub */
        $stub = Stub::makeEmpty(OauthClientInterface::class, [
            'validateOauthAccessToken' => fn (): OauthAccessTokenValidationResponseTransfer => $this->createValidationResponse(),
            'validateAccessToken' => fn (): OauthAccessTokenValidationResponseTransfer => $this->createValidationResponse(),
        ]);

        return $stub;
    }

    protected function createOauthFacadeStub(): OauthFacadeInterface
    {
        /** @var \Spryker\Zed\Oauth\Business\OauthFacadeInterface $stub */
        $stub = Stub::makeEmpty(OauthFacadeInterface::class, [
            'validateAccessToken' => fn (): OauthAccessTokenValidationResponseTransfer => $this->createValidationResponse(),
        ]);

        return $stub;
    }

    protected function createAclAccessCheckerStub(): AclAccessCheckerInterface
    {
        /** @var \Spryker\Glue\KernelFeature\Security\AclAccessCheckerInterface $stub */
        $stub = Stub::makeEmpty(AclAccessCheckerInterface::class, [
            'hasAccess' => fn (): bool => $this->hasAclAccess,
        ]);

        return $stub;
    }

    protected function createValidationResponse(): OauthAccessTokenValidationResponseTransfer
    {
        if (!$this->isTokenValid) {
            return (new OauthAccessTokenValidationResponseTransfer())->setIsValid(false);
        }

        return (new OauthAccessTokenValidationResponseTransfer())
            ->setIsValid(true)
            ->setOauthUserId(json_encode([static::CLAIM_ID_USER => $this->idUser], JSON_THROW_ON_ERROR))
            ->setOauthScopes($this->oauthScopes);
    }

    protected function assertTestCaseAvailable(): void
    {
        if ($this->testCase === null) {
            $this->fail(sprintf(
                'BackendApiLoginHelper requires the test class to extend %s.',
                AbstractApiTestCase::class,
            ));
        }
    }
}
