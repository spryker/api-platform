<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use BadMethodCallException;
use Spryker\ApiPlatform\State\Trait\LocaleAwareTrait;
use Spryker\ApiPlatform\State\Trait\StoreAwareTrait;
use Spryker\ApiPlatform\State\Trait\UriVariableAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements \ApiPlatform\State\ProcessorInterface<mixed, mixed>
 */
abstract class AbstractProcessor implements ProcessorInterface
{
    use LocaleAwareTrait;
    use StoreAwareTrait;
    use UriVariableAwareTrait;

    protected const string ATTRIBUTE_RESOLVED_RELATIONSHIPS = '_spryker_resolved_relationships';

    protected Operation $operation;

    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->operation = $operation;
        $this->uriVariables = $uriVariables;
        $this->context = $context;

        if ($operation instanceof Post) {
            return $this->processPost($data);
        }

        if ($operation instanceof Patch) {
            return $this->processPatch($data);
        }

        if ($operation instanceof Delete) {
            return $this->processDelete();
        }

        return null;
    }

    /**
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    protected function processPost(mixed $data): mixed
    {
        throw new BadMethodCallException(sprintf(
            '%s receives a POST operation but does not implement processPost(). '
            . 'Override the processPost() method in %s to handle POST requests.',
            static::class,
            static::class,
        ));
    }

    /**
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    protected function processPatch(mixed $data): mixed
    {
        throw new BadMethodCallException(sprintf(
            '%s receives a PATCH operation but does not implement processPatch(). '
            . 'Override the processPatch() method in %s to handle PATCH requests.',
            static::class,
            static::class,
        ));
    }

    /**
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    protected function processDelete(): mixed
    {
        throw new BadMethodCallException(sprintf(
            '%s receives a DELETE operation but does not implement processDelete(). '
            . 'Override the processDelete() method in %s to handle DELETE requests.',
            static::class,
            static::class,
        ));
    }

    protected function getOperation(): Operation
    {
        return $this->operation;
    }

    protected function hasRequest(): bool
    {
        return isset($this->context['request']);
    }

    protected function getRequest(): Request
    {
        return $this->context['request'];
    }

    /**
     * Stores relationship resources resolved during processing on the current Request so that the
     * JSON:API response subscriber can emit them as `included` data without re-fetching.
     *
     * @param array<object> $resources
     */
    protected function setResolvedRelationships(?Request $request, string $relationshipName, array $resources): void
    {
        if ($request === null) {
            return;
        }

        $existing = $request->attributes->get(static::ATTRIBUTE_RESOLVED_RELATIONSHIPS, []);
        $existing[$relationshipName] = $resources;
        $request->attributes->set(static::ATTRIBUTE_RESOLVED_RELATIONSHIPS, $existing);
    }
}
