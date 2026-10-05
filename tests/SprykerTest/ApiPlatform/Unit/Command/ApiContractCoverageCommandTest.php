<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Unit\Command;

use Codeception\Test\Unit;
use Spryker\ApiPlatform\Command\ApiContractCoverageCommand;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageBaseline;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageConsoleRenderer;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageEnforcement;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageMarkdownRenderer;
use SprykerTest\ApiPlatform\Unit\Coverage\Fixture\ErrorCodeGapContractCoverageRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Unit
 * @group Command
 * @group ApiContractCoverageCommandTest
 * Add your own group annotations below this line
 */
class ApiContractCoverageCommandTest extends Unit
{
    public function testGivenAGapInAReportedDimensionWhenRunningThenTheGatePasses(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(new ErrorCodeGapContractCoverageRunner());

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $this->assertStringContainsString('Error codes           0/1 covered · 1 uncovered · 0 stale  (reported)', $commandTester->getDisplay());
    }

    public function testGivenTheEnforceOptionWhenRunningThenTheNamedDimensionFailsTheGate(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(new ErrorCodeGapContractCoverageRunner());

        // Act
        $exitCode = $commandTester->execute(['--enforce' => ['error-codes']]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode, $commandTester->getDisplay());
        $this->assertStringContainsString('Contract coverage gate: FAIL (1 uncovered error code(s))', $commandTester->getDisplay());
    }

    public function testGivenAConfiguredDimensionWhenRunningWithAnotherEnforceOptionThenTheConfiguredOneStillFails(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(
            new ErrorCodeGapContractCoverageRunner(ContractCoverageEnforcement::fromValues(['error-codes'])),
        );

        // Act
        $exitCode = $commandTester->execute(['--enforce' => ['includes']]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode, $commandTester->getDisplay());
    }

    public function testGivenAnEnforcedGapTheBaselineNamesWhenRunningThenTheGatePassesAndListsItWithItsReason(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(new ErrorCodeGapContractCoverageRunner(
            ContractCoverageEnforcement::fromValues(['error-codes']),
            ContractCoverageBaseline::fromConfiguration(['error-codes' => ['GET /declared 404' => 'The declared lookup never answers not found.']]),
        ));

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $exitCode, $commandTester->getDisplay());
        $this->assertStringContainsString('  - GET /declared 404 — The declared lookup never answers not found.', $commandTester->getDisplay());
    }

    public function testGivenABaselineEntryNamingNoGapWhenRunningOverTheWholeScopeThenTheGateFails(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(new ErrorCodeGapContractCoverageRunner(
            baseline: ContractCoverageBaseline::fromConfiguration(['error-codes' => ['GET /declared 409' => 'A conflict is answered with the wrong code.']]),
        ));

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode, $commandTester->getDisplay());
        $this->assertStringContainsString('1 error code baseline entry(ies) to remove', $commandTester->getDisplay());
    }

    public function testGivenAnUnknownEnforceValueWhenRunningThenItFailsNamingTheKnownDimensions(): void
    {
        // Arrange
        $commandTester = $this->createCommandTester(new ErrorCodeGapContractCoverageRunner());

        // Act
        $exitCode = $commandTester->execute(['--enforce' => ['error-code']]);

        // Assert
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unknown contract-coverage dimension "error-code"', $commandTester->getDisplay());
    }

    protected function createCommandTester(ErrorCodeGapContractCoverageRunner $runner): CommandTester
    {
        return new CommandTester(new ApiContractCoverageCommand(
            codecept_data_dir(),
            $runner,
            new ContractCoverageConsoleRenderer(),
            new ContractCoverageMarkdownRenderer(),
            new Filesystem(),
        ));
    }
}
