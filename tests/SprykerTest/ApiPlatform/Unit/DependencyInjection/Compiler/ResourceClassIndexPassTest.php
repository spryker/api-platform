<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\DependencyInjection\Compiler;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\DependencyInjection\Compiler\ResourceClassIndexPass;
use Spryker\ApiPlatform\Exception\ResourceClassIndexException;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group DependencyInjection
 * @group Compiler
 * @group ResourceClassIndexPassTest
 * Add your own group annotations below this line
 */
class ResourceClassIndexPassTest extends Unit
{
    protected ApiUnitTester $tester;

    /**
     * The fake resource classes can be loaded into the process only once, so the whole
     * compile-and-verify flow lives in a single test method.
     */
    public function testGivenGeneratedResourceClassesWhenCompilingThenIndexParameterContainsShortNameClassAndCodeBucket(): void
    {
        // Arrange
        $generatedDir = sprintf('%s/api-platform-index-pass-test-%s', sys_get_temp_dir(), uniqid());
        $resourceDirectory = sprintf('%s/Passfront', $generatedDir);
        mkdir($resourceDirectory, 0777, true);

        file_put_contents(sprintf('%s/FoosPassfrontResource.php', $resourceDirectory), <<<'PHP'
<?php

namespace Generated\Api\Passfront;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(shortName: 'foos', extraProperties: ['includedSortPriority' => 100])]
class FoosPassfrontResource
{
}
PHP);
        file_put_contents(sprintf('%s/FoosEUPassfrontResource.php', $resourceDirectory), <<<'PHP'
<?php

namespace Generated\Api\Passfront;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(shortName: 'foos')]
class FoosEUPassfrontResource
{
    public const string CODE_BUCKET = 'EU';
}
PHP);

        // The generated dir is configured with an unresolved placeholder, as in the real container.
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', dirname($generatedDir));
        $container->setParameter('spryker_api_platform.generated_dir', sprintf('%%kernel.project_dir%%/%s', basename($generatedDir)));
        $container->setParameter('spryker_api_platform.api_types', ['Passfront']);

        // Act
        (new ResourceClassIndexPass())->process($container);

        // Assert
        $this->assertEquals([
            'Generated\\Api\\Passfront\\FoosPassfrontResource' => [
                'EU' => [
                    'shortName' => 'foos',
                    'className' => 'Generated\\Api\\Passfront\\FoosEUPassfrontResource',
                    'includedSortPriority' => null,
                ],
                '' => [
                    'shortName' => 'foos',
                    'className' => 'Generated\\Api\\Passfront\\FoosPassfrontResource',
                    'includedSortPriority' => 100,
                ],
            ],
        ], $container->getParameter(ResourceClassIndexPass::PARAMETER_RESOURCE_CLASS_INDEX));

        array_map('unlink', glob(sprintf('%s/*.php', $resourceDirectory)) ?: []);
        rmdir($resourceDirectory);
        rmdir($generatedDir);
    }

    public function testGivenMissingConfigurationParametersWhenCompilingThenIndexParameterIsEmpty(): void
    {
        // Arrange
        $container = new ContainerBuilder();

        // Act
        (new ResourceClassIndexPass())->process($container);

        // Assert
        $this->assertSame([], $container->getParameter(ResourceClassIndexPass::PARAMETER_RESOURCE_CLASS_INDEX));
    }

    public function testGivenExistingButEmptyResourceDirectoryWhenCompilingThenIndexParameterIsEmptyAndNoExceptionIsThrown(): void
    {
        // Arrange: the fresh-environment state — api:generate's own boot compiles the container
        // after ensureGeneratedDirectoriesExist() created the directory but before anything is generated.
        $generatedDir = sprintf('%s/api-platform-index-pass-empty-test-%s', sys_get_temp_dir(), uniqid());
        $resourceDirectory = sprintf('%s/Emptyfront', $generatedDir);
        mkdir($resourceDirectory, 0777, true);

        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.generated_dir', $generatedDir);
        $container->setParameter('spryker_api_platform.api_types', ['Emptyfront']);

        // Act
        try {
            (new ResourceClassIndexPass())->process($container);
        } finally {
            rmdir($resourceDirectory);
            rmdir($generatedDir);
        }

        // Assert
        $this->assertSame([], $container->getParameter(ResourceClassIndexPass::PARAMETER_RESOURCE_CLASS_INDEX));
    }

    public function testGivenResourceFilesThatYieldNoEntriesWhenCompilingThenExceptionIsThrown(): void
    {
        // Arrange: a resource file without an #[ApiResource] attribute compiles to no entry —
        // a silently empty index would disable code bucket filtering for every resource.
        $generatedDir = sprintf('%s/api-platform-index-pass-broken-test-%s', sys_get_temp_dir(), uniqid());
        $resourceDirectory = sprintf('%s/Failfront', $generatedDir);
        mkdir($resourceDirectory, 0777, true);

        file_put_contents(sprintf('%s/BrokenFailfrontResource.php', $resourceDirectory), <<<'PHP'
<?php

namespace Generated\Api\Failfront;

class BrokenFailfrontResource
{
}
PHP);

        $container = new ContainerBuilder();
        $container->setParameter('spryker_api_platform.generated_dir', $generatedDir);
        $container->setParameter('spryker_api_platform.api_types', ['Failfront']);

        // Assert
        $this->expectException(ResourceClassIndexException::class);

        // Act
        try {
            (new ResourceClassIndexPass())->process($container);
        } finally {
            array_map('unlink', glob(sprintf('%s/*.php', $resourceDirectory)) ?: []);
            rmdir($resourceDirectory);
            rmdir($generatedDir);
        }
    }
}
