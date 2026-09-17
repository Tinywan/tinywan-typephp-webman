<?php

namespace TypePhp\Build;

use RuntimeException;
use TypePhp\Analysis\CompilationStatistics;

/** Resolves the Composer-provided runtime sources used by a Nano build. */
final class NanoSourceComposer
{
    /**
     * @return array{
     *     packages: list<ComposerNativePackage>,
     *     packageSources: list<string>,
     *     includeDirs: list<string>,
     *     defines: list<string>,
     *     registry: string,
     *     selection: NanoExtensionSelection|null
     * }
     */
    public function compose(
        string $buildDir,
        string $targetName,
        bool $registerProject,
        ?CompilationStatistics $statistics = null,
    ): array
    {
        $runtime = ComposerNativePackage::load('swoole/php-nano');
        $phpx = ComposerNativePackage::load('swoole/phpx');
        if ($runtime->abi !== $phpx->abi) {
            throw new RuntimeException(
                "Native ABI mismatch: swoole/php-nano={$runtime->abi}, swoole/phpx={$phpx->abi}"
            );
        }

        $packagesByName = [
            $runtime->name => $runtime,
            $phpx->name => $phpx,
        ];
        foreach (ComposerNativePackage::discover() as $package) {
            $packagesByName[$package->name] = $package;
        }
        $packages = array_values($packagesByName);

        $availableExtensions = [];
        foreach ($packages as $package) {
            if ($package->extensionName !== null) {
                $availableExtensions[] = $package->extensionName;
            }
            foreach ($package->components as $component) {
                $availableExtensions[] = $component->extension;
            }
        }
        $selection = $statistics === null
            ? null
            : (new NanoExtensionSelector())->select(
                $statistics,
                array_values(array_unique($availableExtensions)),
            );
        $activeComponents = $selection === null
            ? null
            : $this->selectComponents($packages, $selection);

        $packageSources = [];
        $includeDirs = [];
        $defines = [];
        foreach ($packages as $package) {
            if ($package->abi !== $runtime->abi) {
                throw new RuntimeException(
                    "Native ABI mismatch: {$package->name}={$package->abi}, "
                    . "swoole/php-nano={$runtime->abi}"
                );
            }
            if ($activeComponents === null || $package->components === []) {
                array_push($packageSources, ...$package->sources);
            } else {
                $ownedSources = [];
                foreach ($package->components as $component) {
                    foreach ($component->sources as $source) {
                        $ownedSources[$source] = true;
                    }
                    if (isset($activeComponents[$package->name][$component->name])) {
                        array_push($packageSources, ...$component->sources);
                        array_push($defines, ...$component->defines);
                    }
                }
                foreach ($package->sources as $source) {
                    if (!isset($ownedSources[$source])) {
                        $packageSources[] = $source;
                    }
                }
            }
            array_push($includeDirs, ...$package->includeDirs);
        }
        if ($activeComponents !== null) {
            $defines[] = 'PHP_NANO_SELECTIVE=1';
        }

        return [
            'packages' => $packages,
            'packageSources' => array_values(array_unique($packageSources)),
            'includeDirs' => array_values(array_unique($includeDirs)),
            'defines' => array_values(array_unique($defines)),
            'selection' => $selection,
            'registry' => $this->writeExtensionRegistry(
                $buildDir,
                $targetName,
                $packages,
                $registerProject,
                $activeComponents,
            ),
        ];
    }

    /**
     * @param list<ComposerNativePackage> $packages
     * @return array<string, array<string, true>>
     */
    private function selectComponents(
        array $packages,
        NanoExtensionSelection $selection,
    ): array {
        $extensionSet = array_fill_keys($selection->extensions, true);
        $featureSet = array_fill_keys($selection->features, true);
        $componentsByName = [];
        foreach ($packages as $package) {
            foreach ($package->components as $component) {
                $componentsByName[$component->name][] = [$package, $component];
            }
        }

        $active = [];
        $pending = [];
        foreach ($packages as $package) {
            foreach ($package->components as $component) {
                if (isset($extensionSet[$component->extension])
                    || isset($featureSet[$component->name])) {
                    $active[$package->name][$component->name] = true;
                    $pending[] = $component;
                }
            }
        }
        while (($component = array_pop($pending)) !== null) {
            foreach ($component->requires as $requirement) {
                if (!isset($componentsByName[$requirement])) {
                    throw new RuntimeException(
                        "Nano component `{$component->name}` requires unknown component `{$requirement}`"
                    );
                }
                foreach ($componentsByName[$requirement] ?? [] as [$package, $required]) {
                    if (!isset($active[$package->name][$required->name])) {
                        $active[$package->name][$required->name] = true;
                        $pending[] = $required;
                    }
                }
            }
        }
        return $active;
    }

    /** @param list<ComposerNativePackage> $packages */
    private function writeExtensionRegistry(
        string $buildDir,
        string $targetName,
        array $packages,
        bool $registerProject,
        ?array $activeComponents,
    ): string {
        if (!is_dir($buildDir) && !mkdir($buildDir, 0777, true) && !is_dir($buildDir)) {
            throw new RuntimeException("Unable to create Nano build directory: {$buildDir}");
        }

        $extensions = array_values(array_filter(
            $packages,
            static fn(ComposerNativePackage $package): bool => $package->kind === 'extension',
        ));
        $path = $buildDir . DIRECTORY_SEPARATOR . 'composer_extensions.cpp';
        $declarations = [];
        $entries = [];
        foreach ($packages as $package) {
            foreach ($package->components as $component) {
                if (($activeComponents !== null
                        && !isset($activeComponents[$package->name][$component->name]))
                    || $component->moduleEntry === null) {
                    continue;
                }
                    $moduleEntry = $component->moduleEntry;
                    $declarations[$moduleEntry] = "extern \"C\" zend_module_entry {$moduleEntry};";
                    $entries[$moduleEntry] = "    &{$moduleEntry},";
            }
        }
        foreach ($extensions as $extension) {
            if ($activeComponents !== null && $extension->components !== []) {
                $packageActive = false;
                foreach ($extension->components as $component) {
                    if (isset($activeComponents[$extension->name][$component->name])) {
                        $packageActive = true;
                        break;
                    }
                }
                if (!$packageActive) {
                    continue;
                }
            }
            $moduleEntry = $extension->extensionModuleEntry;
            $declarations[$moduleEntry] = "extern \"C\" zend_module_entry {$moduleEntry};";
            $entries[$moduleEntry] = "    &{$moduleEntry},";
        }
        if ($registerProject) {
            $namespace = 'typephp_project_' . $targetName;
            $moduleEntry = 'typephp_' . $targetName . '_module_entry';
            $declarations[$moduleEntry] = "namespace {$namespace} { extern zend_module_entry {$moduleEntry}; }";
            $entries[$moduleEntry] = "    &{$namespace}::{$moduleEntry},";
        }

        $count = count($entries);
        $storageSize = max(1, $count);
        $contents = "#include <php_nano_extension.h>\n\n"
            . implode("\n", array_values($declarations)) . "\n\n"
            . "extern \"C\" zend_result php_nano_startup_composer_extensions() {\n"
            . "    static zend_module_entry *extensions[{$storageSize}] = {\n"
            . implode("\n", array_values($entries)) . "\n"
            . "    };\n"
            . "    return php_nano_startup_extensions(extensions, {$count});\n"
            . "}\n\n"
            . "extern \"C\" void php_nano_shutdown_composer_extensions() {\n"
            . "    php_nano_shutdown_extensions();\n"
            . "}\n";
        if (!is_file($path) || file_get_contents($path) !== $contents) {
            if (file_put_contents($path, $contents) === false) {
                throw new RuntimeException("Unable to write Nano extension registry: {$path}");
            }
        }
        return $path;
    }
}
