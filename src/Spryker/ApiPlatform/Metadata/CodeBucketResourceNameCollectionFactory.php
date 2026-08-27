<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Metadata;

use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceNameCollection;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ResourceClassIndexPass;
use Spryker\ApiPlatform\Exception\ResourceClassIndexException;

/**
 * Filters the resource name collection based on APPLICATION_CODE_BUCKET.
 *
 * Selection rule per base resource class: the variant generated for the current code bucket wins,
 * the base resource (no code bucket) is the fallback, variants of other code buckets are excluded.
 * When no code bucket is set, only base resources are included.
 *
 * The class-to-bucket metadata comes from the compile-time index parameter
 * ({@see \Spryker\ApiPlatform\DependencyInjection\Compiler\ResourceClassIndexPass}), so no
 * reflection runs at request time.
 */
class CodeBucketResourceNameCollectionFactory implements ResourceNameCollectionFactoryInterface
{
    use CodeBucketResolverTrait;

    protected const string GENERATED_RESOURCE_NAMESPACE_PREFIX = 'Generated\\Api\\';

    /**
     * @var array<string, \ApiPlatform\Metadata\Resource\ResourceNameCollection>
     */
    protected array $resourceNameCollectionCache = [];

    /**
     * @param array<class-string, array<string, array{shortName: string, className: class-string, includedSortPriority: int|null}>> $resourceClassIndex
     */
    public function __construct(
        protected readonly ResourceNameCollectionFactoryInterface $decorated,
        protected readonly array $resourceClassIndex = [],
    ) {
    }

    public function create(): ResourceNameCollection
    {
        $currentCodeBucket = $this->getCurrentCodeBucket();

        if (isset($this->resourceNameCollectionCache[$currentCodeBucket])) {
            return $this->resourceNameCollectionCache[$currentCodeBucket];
        }

        return $this->resourceNameCollectionCache[$currentCodeBucket] = $this->createForCodeBucket($currentCodeBucket);
    }

    /**
     * @throws \Spryker\ApiPlatform\Exception\ResourceClassIndexException
     */
    protected function createForCodeBucket(string $currentCodeBucket): ResourceNameCollection
    {
        $indexedClassNames = [];
        $selectedClassNames = [];

        foreach ($this->resourceClassIndex as $indexEntriesByCodeBucket) {
            foreach ($indexEntriesByCodeBucket as $indexEntry) {
                $indexedClassNames[$indexEntry['className']] = true;
            }

            $selectedIndexEntry = $indexEntriesByCodeBucket[$currentCodeBucket]
                ?? $indexEntriesByCodeBucket[ResourceClassIndexPass::CODE_BUCKET_KEY_BASE]
                ?? null;

            if ($selectedIndexEntry !== null) {
                $selectedClassNames[$selectedIndexEntry['className']] = true;
            }
        }

        $filteredResourceClasses = [];

        foreach ($this->decorated->create() as $resourceClass) {
            // Generated classes visible while the index is empty mean the container was compiled
            // before api:generate — filtering would silently stay off for every code bucket.
            if ($this->resourceClassIndex === [] && str_starts_with($resourceClass, static::GENERATED_RESOURCE_NAMESPACE_PREFIX)) {
                throw new ResourceClassIndexException(sprintf(
                    'The resource class index is empty although the generated resource class "%s" exists. The dependency injection container was compiled before api:generate — rebuild it with `vendor/bin/glue cache:clear` (per GLUE_APPLICATION).',
                    $resourceClass,
                ));
            }

            if (isset($indexedClassNames[$resourceClass]) && !isset($selectedClassNames[$resourceClass])) {
                continue;
            }

            $filteredResourceClasses[] = $resourceClass;
        }

        return new ResourceNameCollection($filteredResourceClasses);
    }
}
