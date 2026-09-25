<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\ApiPlatform\Command;

use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageConsoleRenderer;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageMarkdownRenderer;
use Spryker\ApiPlatform\Contract\Coverage\ContractCoverageRunner;
use Spryker\ApiPlatform\Contract\Coverage\Exception\ResourcesNotGeneratedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Reports which API operations, validation rules and required response attributes the tests cover,
 * and fails on a gap.
 *
 * Registered only when development console commands are enabled — it reads the test suites'
 * coverage annotations, so it belongs to the development toolchain and never ships as a runtime
 * command.
 */
class ApiContractCoverageCommand extends Command
{
    protected const string NAME = 'api:contract:coverage';

    protected const string OPTION_MODULE = 'module';

    protected const string OPTION_MODULE_SHORTCUT = 'm';

    protected const string OPTION_SUMMARY_OUT = 'summary-out';

    protected const int CODE_SUCCESS = 0;

    protected const int CODE_ERROR = 1;

    public function __construct(
        protected readonly string $applicationRoot,
        protected readonly ContractCoverageRunner $contractCoverageRunner,
        protected readonly ContractCoverageConsoleRenderer $consoleRenderer,
        protected readonly ContractCoverageMarkdownRenderer $markdownRenderer,
        protected readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName(static::NAME)
            ->setDescription('Report API contract coverage and fail on any gap')
            ->addOption(
                static::OPTION_MODULE,
                static::OPTION_MODULE_SHORTCUT,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Only check these modules or resources. Casing, separators and a trailing plural are ignored, '
                . 'so "Wishlist", "wishlist" and "wishlists" all select the wishlists resource. '
                . 'Repeat the option for more than one. Checks everything in scope when omitted.',
            )
            ->addOption(
                static::OPTION_SUMMARY_OUT,
                null,
                InputOption::VALUE_REQUIRED,
                'Append a markdown report of the gaps to this file, on top of the console output. '
                . 'Pass "$GITHUB_STEP_SUMMARY" on GitHub Actions to publish it on the run summary page.',
            )
            ->setHelp(<<<'HELP'
Reflects the generated API Platform resources against the #[CoversApiOperation],
#[CoversApiValidation] and #[CoversApiRequiredResponseAttributes] annotations on the API test
suites, then reports the coverage counters and every gap, and exits non-zero on any gap or stale
claim.

  <info>%command.full_name%</info>                              check every enforced resource
  <info>%command.full_name% -m Wishlist</info>                  check the wishlists resource
  <info>%command.full_name% -m wishlist-items -m wishlists</info>  check both
  <info>%command.full_name% -v</info>                           also list every covered entry

Runs for the API type of the application it is registered in, and needs that type's resources
generated first: <info>GLUE_APPLICATION=GLUE_STOREFRONT vendor/bin/glue api:generate</info> (or
<info>GLUE_BACKEND</info>).
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var array<string> $moduleFilters */
        $moduleFilters = (array)$input->getOption(static::OPTION_MODULE);

        try {
            $result = $this->contractCoverageRunner->run($this->applicationRoot, $moduleFilters);
        } catch (ResourcesNotGeneratedException $resourcesNotGeneratedException) {
            $output->writeln(sprintf('<error>%s</error>', $resourcesNotGeneratedException->getMessage()));

            return static::CODE_ERROR;
        }

        $output->writeln($this->consoleRenderer->render($result, $output->isVerbose()));

        if ($result->unmatchedFilters !== []) {
            $output->writeln(sprintf(
                "\n<error>Unknown module(s): %s</error>",
                implode(', ', $result->unmatchedFilters),
            ));
            $output->writeln(sprintf(
                'Selectable: %s',
                implode(', ', $this->contractCoverageRunner->getSelectableResources($this->applicationRoot)),
            ));
        }

        if ($result->unknownExclusions !== []) {
            $output->writeln(sprintf(
                "\n<error>Excluded resource(s) that do not exist: %s</error>",
                implode(', ', $result->unknownExclusions),
            ));
            $output->writeln(
                'Drop them from contractCoverageExcludedResources() in the application\'s '
                . 'spryker_api_platform package configuration.',
            );
        }

        $summaryPath = $input->getOption(static::OPTION_SUMMARY_OUT);
        if (is_string($summaryPath) && $summaryPath !== '') {
            $this->writeSummary($output, $summaryPath, $this->markdownRenderer->render($result));
        }

        return $result->isSuccessful() ? static::CODE_SUCCESS : static::CODE_ERROR;
    }

    /**
     * Appends rather than truncates, so several reports can share one sink — `$GITHUB_STEP_SUMMARY`
     * collects the whole job's summary. A sink that cannot be written warns and leaves the exit code
     * alone: a reporting failure must never manufacture or mask a gate verdict.
     */
    protected function writeSummary(OutputInterface $output, string $path, string $markdown): void
    {
        try {
            $this->filesystem->appendToFile($path, $markdown);
        } catch (IOException $ioException) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $errorOutput->writeln(sprintf(
                '<comment>Could not write the coverage summary to %s: %s</comment>',
                $path,
                $ioException->getMessage(),
            ));
        }
    }
}
