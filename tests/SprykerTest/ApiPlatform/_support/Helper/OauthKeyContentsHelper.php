<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Oauth\OauthConstants;
use Spryker\Shared\OauthCryptography\OauthCryptographyConstants;
use SprykerTest\Shared\Testify\Helper\ConfigHelperTrait;

/**
 * Hands the OAuth signing keys to the lane as key *contents* instead of `file://` paths:
 * {@see \League\OAuth2\Server\CryptKey} raises an `E_USER_NOTICE` over the repository keys' 644
 * permissions, which Codeception's error handler turns into a `500` on the host lane.
 *
 * Enable it in any suite that mints or verifies a real token, after
 * {@see \SprykerTest\Shared\Testify\Helper\ConfigHelper}.
 */
class OauthKeyContentsHelper extends Module
{
    use ConfigHelperTrait;

    protected const string FILE_PREFIX = 'file://';

    /**
     * The signing keys of both legs: Zed issues tokens with the Oauth pair, and the Glue resource
     * server verifies them with the OauthCryptography public key, which is its own config entry.
     *
     * @var list<string>
     */
    protected const array KEY_CONFIG_KEYS = [
        OauthConstants::PRIVATE_KEY_PATH,
        OauthConstants::PUBLIC_KEY_PATH,
        OauthCryptographyConstants::PUBLIC_KEY_PATH,
    ];

    public function _before(TestInterface $test): void
    {
        parent::_before($test);

        foreach (static::KEY_CONFIG_KEYS as $configKey) {
            $keyPath = (string)Config::get($configKey);

            if (!str_starts_with($keyPath, static::FILE_PREFIX)) {
                continue;
            }

            $this->setConfig($configKey, (string)file_get_contents($keyPath));
        }
    }
}
