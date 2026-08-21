<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class Customer implements UserInterface
{
    protected string $customerReference;

    protected string $email;

    /**
     * @var array<string>
     */
    protected array $roles;

    /**
     * @param array<string> $roles
     */
    public function __construct(string $customerReference, string $email, array $roles = ['ROLE_USER'])
    {
        $this->customerReference = $customerReference;
        $this->email = $email;
        $this->roles = $roles;
    }

    public function getCustomerReference(): string
    {
        return $this->customerReference;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return array<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->customerReference;
    }
}
