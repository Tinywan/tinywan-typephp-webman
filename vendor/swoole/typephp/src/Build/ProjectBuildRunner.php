<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Translator;

final readonly class ProjectBuildRunner
{
    public function __construct(private Translator $translator)
    {
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): void
    {
        $this->translator->setIndent('    ');
        $sourceFiles = $this->generateProjectSources($arguments);
        $sourceFiles = $this->appendInternalWasmInterface($sourceFiles);

        if ($this->translator->isDryRun()) {
            $this->finishDryRun($sourceFiles);
            return;
        }

        $target = $this->compileAndLink($sourceFiles);
        if ($this->translator->isRunRequested()) {
            $this->translator->run($target);
        }
    }

    /** @param list<string> $arguments @return list<string> */
    private function generateProjectSources(array $arguments): array
    {
        $project = $this->translator->parseArgv($arguments);
        $files = $this->translator->prepare($project);
        return $this->translator->convert($files);
    }

    /** @param list<string> $sourceFiles @return list<string> */
    private function appendInternalWasmInterface(array $sourceFiles): array
    {
        $manifest = $this->environmentValue('TYPEPHP_WASM_INTERFACE_MANIFEST');
        if ($manifest === null) {
            return $sourceFiles;
        }

        $adapter = $this->requiredEnvironmentValue('TYPEPHP_WASM_INTERFACE_ADAPTER');
        $this->translator->writeWasmInterface(
            $manifest,
            $this->requiredEnvironmentValue('TYPEPHP_WASM_INTERFACE_WIT'),
            $adapter,
            $this->requiredEnvironmentValue('TYPEPHP_WASM_INTERFACE_ASYNC_EXPORTS'),
            $this->requiredEnvironmentValue('TYPEPHP_WASM_PACKAGE'),
            $this->requiredEnvironmentValue('TYPEPHP_WASM_WORLD'),
        );
        $sourceFiles[] = $adapter;
        return $sourceFiles;
    }

    private function environmentValue(string $name): ?string
    {
        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function requiredEnvironmentValue(string $name): string
    {
        $value = $this->environmentValue($name);
        if ($value === null) {
            throw new \RuntimeException('Incomplete internal WASM interface configuration: ' . $name);
        }
        return $value;
    }

    /** @param list<string> $sourceFiles */
    private function finishDryRun(array $sourceFiles): void
    {
        $sourceList = $this->environmentValue('TYPEPHP_GENERATED_SOURCE_LIST');
        if ($sourceList !== null) {
            AtomicFile::write($sourceList, implode(PHP_EOL, $sourceFiles) . PHP_EOL);
        }

        $count = count($sourceFiles);
        $buildDir = $this->translator->getBuildDir();
        $this->translator->output(
            "Dry run completed: {$count} C++ source file(s) generated in {$buildDir}",
            'lightBlue',
        );
    }

    /** @param list<string> $sourceFiles */
    private function compileAndLink(array $sourceFiles): string
    {
        $objectFiles = [
            ...$this->translator->compile($sourceFiles),
            ...$this->translator->getProjectObjectFiles(),
        ];
        return $this->translator->build($objectFiles);
    }
}
