<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Security\Resolver;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves the authenticated identity from the Symfony security TokenStorage.
 *
 * The Symfony firewall populates `TokenStorage` with the user identifier — a JSON-encoded
 * payload of OAuth claims. This resolver decodes that payload into a plain associative
 * array. Domain-specific interpretation of the claim keys (e.g. `customer_reference`,
 * `id_company_user`) belongs to the consumer modules, not to this infrastructure layer.
 */
class IdentityResolver
{
    public function __construct(
        protected TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(): ?array
    {
        $token = $this->tokenStorage->getToken();

        if ($token === null || $token->getUser() === null) {
            return null;
        }

        $userIdentifier = $token->getUser()->getUserIdentifier();
        $userData = json_decode($userIdentifier, true);

        if (!is_array($userData)) {
            return null;
        }

        return $userData;
    }
}
