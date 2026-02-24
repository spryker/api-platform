<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CustomerProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $decoded = json_decode(base64_decode($identifier), true);

        if (!is_array($decoded) || !isset($decoded['customer_reference'], $decoded['email'])) {
            throw new UserNotFoundException(sprintf('Customer identifier "%s" could not be decoded', $identifier));
        }

        return new Customer(
            $decoded['customer_reference'],
            $decoded['email'],
            ['ROLE_USER'],
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Customer) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported', get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === Customer::class || is_subclass_of($class, Customer::class);
    }
}
