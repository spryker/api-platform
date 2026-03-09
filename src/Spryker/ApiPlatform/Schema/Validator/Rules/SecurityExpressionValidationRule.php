<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Schema\Validator\Rules;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Validates that security expressions in resource schemas are syntactically
 * correct by linting them against the variables available at runtime
 * (token, user, roles, request, etc.).
 */
class SecurityExpressionValidationRule implements ValidationRuleInterface
{
    protected const array VALID_OPERATIONS = ['Get', 'GetCollection', 'Post', 'Put', 'Patch', 'Delete'];

    /**
     * Variable names available at runtime in security expressions.
     *
     * @see \ApiPlatform\Symfony\Security\ResourceAccessChecker::isGranted()
     * @see \ApiPlatform\Symfony\Security\State\AccessCheckerProvider::provide()
     */
    protected const array EXPRESSION_VARIABLE_NAMES = [
        'token',
        'user',
        'roles',
        'object',
        'previous_object',
        'request',
        'trust_resolver',
        'auth_checker',
    ];

    public function __construct(
        protected readonly ExpressionLanguage $expressionLanguage,
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    public function validate(array $schema): array
    {
        $errors = [];
        $operations = $schema['operations'] ?? [];

        if (!is_array($operations)) {
            return $errors;
        }

        foreach ($operations as $operationType => $operation) {
            if (!in_array($operationType, static::VALID_OPERATIONS, true)) {
                continue;
            }

            if (!is_array($operation)) {
                continue;
            }

            $errors = array_merge(
                $errors,
                $this->validateSecurityExpression($operationType, $operation, $schema),
            );
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $operation
     * @param array<string, mixed> $schema
     *
     * @return array<string>
     */
    protected function validateSecurityExpression(string $operationType, array $operation, array $schema): array
    {
        if (!isset($operation['security'])) {
            return [];
        }

        $security = $operation['security'];

        if (!is_string($security)) {
            return [
                sprintf(
                    'Security expression for operation "%s" must be a string in %s',
                    $operationType,
                    $schema['sourceFile'] ?? 'unknown file',
                ),
            ];
        }

        try {
            $this->expressionLanguage->lint($security, static::EXPRESSION_VARIABLE_NAMES);
        } catch (SyntaxError $e) {
            return [
                sprintf(
                    'Invalid security expression syntax for operation "%s" in %s: %s',
                    $operationType,
                    $schema['sourceFile'] ?? 'unknown file',
                    $e->getMessage(),
                ),
            ];
        }

        return [];
    }
}
