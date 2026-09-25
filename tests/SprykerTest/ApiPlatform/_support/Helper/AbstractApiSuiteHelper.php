<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\ApiPlatform\Helper;

use Codeception\Exception\ModuleException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\TestInterface;
use SprykerTest\Shared\Testify\Helper\BootstrapHelper;
use SprykerTest\Shared\Testify\Helper\LocatorHelper;

/**
 * Registers the module stack an API suite needs, in the order it needs, so a suite's
 * codeception.yml lists this helper instead of repeating the stack.
 *
 * Registration happens in the constructor because a module created while the SuiteManager
 * create-loop still runs joins the suite's module snapshot and keeps its lifecycle hooks and
 * generated actor actions; one created later gets neither.
 *
 * See the API Platform testing guide in the Spryker documentation for the suite-wiring rules.
 */
abstract class AbstractApiSuiteHelper extends Module
{
    /**
     * @var array<string>
     */
    protected array $requiredFields = ['environmentModule'];

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        'application' => null,
        'projectNamespaces' => [],
        'applicationPluginProvider' => [],
        'environmentModule' => null,
    ];

    /**
     * Created before {@see \SprykerTest\Shared\Testify\Helper\LocatorHelper} freezes the Spryker
     * Config.
     *
     * @return array<string>
     */
    abstract protected function getModulesBeforeEnvironment(): array;

    /**
     * @return array<string>
     */
    abstract protected function getModulesAfterEnvironment(): array;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(ModuleContainer $moduleContainer, ?array $config = null)
    {
        parent::__construct($moduleContainer, $config);

        foreach ($this->getModulesBeforeEnvironment() as $moduleName) {
            $this->ensureModule($moduleName);
        }

        $this->ensureModule($this->config['environmentModule']);

        foreach ($this->getModulesAfterEnvironment() as $moduleName) {
            $this->ensureModule($moduleName);
        }

        $this->forwardConfiguration();
    }

    /**
     * @throws \Codeception\Exception\ModuleException
     */
    public function _before(TestInterface $test): void
    {
        $expectedApplication = $this->config['application'];

        if ($expectedApplication === null || !defined('APPLICATION') || $expectedApplication === APPLICATION) {
            return;
        }

        throw new ModuleException($this, sprintf(
            'APPLICATION is already defined as "%s", but this suite needs "%s". APPLICATION is a '
            . 'process-global constant that cannot be redefined, so this suite has to run in its own '
            . 'codecept process: `codecept run -c <path/to/codeception.yml> <SuiteName>`. It must also '
            . 'be excluded from any run that boots suites of another application '
            . '(`codecept run --skip <SuiteName>`).',
            APPLICATION,
            $expectedApplication,
        ));
    }

    protected function ensureModule(string $moduleName): void
    {
        $moduleName = $this->normalizeModuleName($moduleName);

        if ($this->moduleContainer->hasModule($moduleName)) {
            return;
        }

        $this->moduleContainer->create($moduleName);
    }

    /**
     * Codeception addresses a class-based module by its FQCN with a leading backslash, while a
     * bundled module such as `Asserts` is addressed by its bare name.
     */
    protected function normalizeModuleName(string $moduleName): string
    {
        if (!str_contains($moduleName, '\\')) {
            return $moduleName;
        }

        return '\\' . ltrim($moduleName, '\\');
    }

    protected function forwardConfiguration(): void
    {
        if ($this->config['applicationPluginProvider']) {
            $this->setModuleConfig(BootstrapHelper::class, [
                'applicationPluginProvider' => $this->config['applicationPluginProvider'],
            ]);
        }

        if ($this->config['application']) {
            $this->setModuleConfig($this->config['environmentModule'], [
                'application' => $this->config['application'],
            ]);
        }

        if ($this->config['projectNamespaces']) {
            $this->setModuleConfig(LocatorHelper::class, [
                'projectNamespaces' => $this->config['projectNamespaces'],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function setModuleConfig(string $moduleName, array $config): void
    {
        $this->moduleContainer->getModule($this->normalizeModuleName($moduleName))->_setConfig($config);
    }
}
