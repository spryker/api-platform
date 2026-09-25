<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Coverage;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Contract\Coverage\Exception\ResourcesNotGeneratedException;
use SprykerTest\ApiPlatform\Coverage\ContractCoverageFactory;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ExcludedUndeclaredResponsesContractCoverageRunner;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\IdentifierDeclarationContractCoverageRunner;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesContractCoverageRunner;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesDeclaringCoverageFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ResponseAttributesMarkedCoverageFixture;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\SameShortNameContractCoverageRunner;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UndeclaredResponsesContractCoverageRunner;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\UnknownExclusionContractCoverageRunner;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Coverage
 * @group ContractCoverageRunnerTest
 * Add your own group annotations below this line
 */
class ContractCoverageRunnerTest extends Unit
{
    protected const string APPLICATION_ROOT_UNUSED_BY_SEAM = '';

    protected const string APPLICATION_ROOT_WITHOUT_GENERATED_RESOURCES = '/nonexistent-application-root';

    public function testGivenNoGeneratedResourcesWhenRunningThenItFailsInsteadOfReportingAnEmptyPass(): void
    {
        // Arrange — a root with no src/Generated/Api/Storefront, as any lane that skipped api:generate
        $runner = ContractCoverageFactory::createContractCoverageRunner('Storefront');

        // Assert — every counter would read zero and the gate would pass while checking nothing,
        // so the absence of generated resources has to be an error rather than a verdict.
        $this->expectException(ResourcesNotGeneratedException::class);
        $this->expectExceptionMessageMatches('/vendor\/bin\/glue api:generate/');

        // Act
        $runner->run(static::APPLICATION_ROOT_WITHOUT_GENERATED_RESOURCES);
    }

    public function testGivenTwoResourceClassesSharingAShortNameWhenRunningThenTheOperationsOfBothClassesAreDemanded(): void
    {
        // Arrange - nothing is excluded, so both classes' operations are enforced.
        $runner = new SameShortNameContractCoverageRunner();

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert — keying the truth per class instead of per short name would drop the second
        // class's operations from the gate.
        $uncoveredKeys = array_map(
            static fn ($operation) => $operation->key(),
            $result->report->uncoveredOperations,
        );
        $this->assertContains('GET /same-short-name-fixture', $uncoveredKeys);
        $this->assertContains('GET /parents/{parentReference}/same-short-name-fixture', $uncoveredKeys);
    }

    public function testGivenAnEnforcedOperationDeclaringNoResponsesWhenRunningThenItIsReportedAsASchemaDefectAndTheGateFails(): void
    {
        // Arrange — nothing is excluded, so the fixture resource is enforced.
        $runner = new UndeclaredResponsesContractCoverageRunner();

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert
        $this->assertCount(1, $result->schemaDefects);
        $this->assertSame('undeclared', $result->schemaDefects[0]->resource);
        $this->assertSame('PATCH /undeclared/{uuid}', $result->schemaDefects[0]->operation->dispatchKey());
        $this->assertFalse($result->isSuccessful());
        $this->assertContains('1 operation(s) without schema-declared responses', $result->failureReasons());
    }

    public function testGivenAnExclusionNamingNoGeneratedResourceWhenRunningThenItIsReportedAndTheGateFails(): void
    {
        // Arrange — an exclusion left behind by a resource that was renamed or removed.
        $runner = new UnknownExclusionContractCoverageRunner();

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert — an exclusion that protects nothing has to be visible, or the list rots into a
        // set of lines nobody can tell apart from the ones still doing work.
        $this->assertSame(['renamed-away'], $result->unknownExclusions);
        $this->assertFalse($result->isSuccessful());
        $this->assertContains(
            'excluded resource(s) that do not exist: renamed-away',
            $result->failureReasons(),
        );
    }

    public function testGivenAnExcludedOperationDeclaringNoResponsesWhenRunningThenNoSchemaDefectIsReported(): void
    {
        // Arrange — the same generated class, with its resource excluded from the gate.
        $runner = new ExcludedUndeclaredResponsesContractCoverageRunner();

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert — the defect check is enforced-scope-only, so excluded resources stay silent.
        $this->assertSame([], $result->schemaDefects);
        $this->assertTrue($result->isSuccessful());
    }

    public function testGivenAnOperationDeclaredWithoutTheResponseAttributeMarkerWhenRunningThenEveryAttributeOfEveryOperationIsAGap(): void
    {
        // Arrange — nothing is excluded, so the fixture resource is enforced.
        $runner = new ResponseAttributesContractCoverageRunner(
            [],
            [ResponseAttributesDeclaringCoverageFixture::class],
        );

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert — declaring the operation is not claiming its response body, so the declared GET
        // is as uncovered as the undeclared POST.
        $this->assertSame(
            [
                'GET /response-attributes-fixture  name',
                'GET /response-attributes-fixture  lines[].sku',
                'GET /response-attributes-fixture  lines[].quantity',
                'GET /response-attributes-fixture  tags',
                'GET /response-attributes-fixture  pagination',
                'POST /response-attributes-fixture  name',
                'POST /response-attributes-fixture  lines[].sku',
                'POST /response-attributes-fixture  lines[].quantity',
                'POST /response-attributes-fixture  tags',
                'POST /response-attributes-fixture  pagination',
            ],
            $this->responseAttributeKeys($result->report->uncoveredResponseAttributes),
        );
        $this->assertSame([], $this->responseAttributeKeys($result->report->coveredResponseAttributes));
        $this->assertFalse($result->isSuccessful());
        $this->assertContains('10 uncovered response attribute(s)', $result->failureReasons());
        $this->assertSame(
            [ResponseAttributesDeclaringCoverageFixture::class . '::testGivenTheFixtureResourceWhenGetCollectionThenItIsReturned'],
            $result->operationDeclarers['GET /response-attributes-fixture'],
        );
    }

    public function testGivenTheResponseAttributeMarkerOnOneOperationWhenRunningThenOnlyTheOtherOperationsAttributesRemainAGap(): void
    {
        // Arrange
        $runner = new ResponseAttributesContractCoverageRunner(
            [],
            [ResponseAttributesMarkedCoverageFixture::class],
        );

        // Act
        $result = $runner->run(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert — one marker closes every attribute of the operation it names, and only that one.
        $this->assertSame(
            [
                'POST /response-attributes-fixture  name',
                'POST /response-attributes-fixture  lines[].sku',
                'POST /response-attributes-fixture  lines[].quantity',
                'POST /response-attributes-fixture  tags',
                'POST /response-attributes-fixture  pagination',
            ],
            $this->responseAttributeKeys($result->report->uncoveredResponseAttributes),
        );
        $this->assertSame(
            [
                'GET /response-attributes-fixture  name',
                'GET /response-attributes-fixture  lines[].sku',
                'GET /response-attributes-fixture  lines[].quantity',
                'GET /response-attributes-fixture  tags',
                'GET /response-attributes-fixture  pagination',
            ],
            $this->responseAttributeKeys($result->report->coveredResponseAttributes),
        );
    }

    public function testGivenAnExcludedResourceWhenReadingResponseAttributesThenEveryGeneratedResourceIsStillReturned(): void
    {
        // Arrange — the runtime check asks about the operation the running test declares, not about
        // the gate's scope, so excluding the resource from the gate must not empty the answer.
        $contractCoverageRunner = new ResponseAttributesContractCoverageRunner(['response-attributes-fixture']);

        // Act
        $responseAttributes = $contractCoverageRunner->responseAttributes(static::APPLICATION_ROOT_UNUSED_BY_SEAM);

        // Assert
        $this->assertSame(
            [
                'GET /response-attributes-fixture' => ['name', 'lines[].sku', 'lines[].quantity', 'tags', 'pagination'],
                'POST /response-attributes-fixture' => ['name', 'lines[].sku', 'lines[].quantity', 'tags', 'pagination'],
            ],
            $responseAttributes,
        );
    }

    public function testGivenResourcesDeclaringAnIdentifierAndNotWhenReadingThemThenOnlyTheDeclaringOnesAreReturned(): void
    {
        // Arrange
        $contractCoverageRunner = new IdentifierDeclarationContractCoverageRunner();

        // Act
        $shortNames = $contractCoverageRunner->resourceShortNamesDeclaringIdentifier(
            static::APPLICATION_ROOT_UNUSED_BY_SEAM,
        );

        // Assert
        $this->assertSame(['undeclared', 'unreadable-identifier'], $shortNames);
    }

    public function testGivenAnIdentifierMarkedUnreadableWhenReadingIdentifierDeclarationsThenItStillCountsAsDeclared(): void
    {
        // Arrange — `readable: false` on an identifier only keeps the value out of the `attributes`
        // object; `data.id` still carries it, so readability must not decide the exemption.
        $contractCoverageRunner = new IdentifierDeclarationContractCoverageRunner();

        // Act
        $shortNames = $contractCoverageRunner->resourceShortNamesDeclaringIdentifier(
            static::APPLICATION_ROOT_UNUSED_BY_SEAM,
        );

        // Assert
        $this->assertContains('unreadable-identifier', $shortNames);
    }

    public function testGivenAnExcludedResourceWhenReadingIdentifierDeclarationsThenEveryGeneratedResourceIsStillConsidered(): void
    {
        // Arrange — like the other runtime lookups, this one answers about the resource a running
        // test exercised, not about the gate's scope.
        $contractCoverageRunner = new IdentifierDeclarationContractCoverageRunner(['undeclared']);

        // Act
        $shortNames = $contractCoverageRunner->resourceShortNamesDeclaringIdentifier(
            static::APPLICATION_ROOT_UNUSED_BY_SEAM,
        );

        // Assert
        $this->assertNotContains('identifierless', $shortNames);
        $this->assertContains('undeclared', $shortNames);
    }

    /**
     * @param array<\Spryker\ApiPlatform\Contract\Coverage\ResponseAttribute> $responseAttributes
     *
     * @return array<string>
     */
    protected function responseAttributeKeys(array $responseAttributes): array
    {
        return array_map(static fn ($responseAttribute) => $responseAttribute->key(), $responseAttributes);
    }
}
