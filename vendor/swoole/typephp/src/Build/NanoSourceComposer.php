<?php

namespace TypePhp\Build;

use RuntimeException;

/** Resolves the Composer-provided runtime sources used by a Nano build. */
final class NanoSourceComposer
{
    /**
     * @return array{
     *     packages: list<ComposerNativePackage>,
     *     packageSources: list<string>,
     *     includeDirs: list<string>,
     *     registry: string
     * }
     */
    public function compose(string $buildDir, string $targetName, bool $registerProject): array
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

        $packageSources = [];
        $includeDirs = [];
        foreach ($packages as $package) {
            if ($package->abi !== $runtime->abi) {
                throw new RuntimeException(
                    "Native ABI mismatch: {$package->name}={$package->abi}, "
                    . "swoole/php-nano={$runtime->abi}"
                );
            }
            array_push($packageSources, ...$package->sources);
            array_push($includeDirs, ...$package->includeDirs);
        }

        return [
            'packages' => $packages,
            'packageSources' => array_values(array_unique($packageSources)),
            'includeDirs' => array_values(array_unique($includeDirs)),
            'registry' => $this->writeExtensionRegistry(
                $buildDir,
                $targetName,
                $packages,
                $registerProject,
            ),
        ];
    }

    /** @param list<ComposerNativePackage> $packages */
    private function writeExtensionRegistry(
        string $buildDir,
        string $targetName,
        array $packages,
        bool $registerProject,
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
        foreach ($extensions as $extension) {
            $moduleEntry = $extension->extensionModuleEntry;
            $declarations[] = "extern \"C\" zend_module_entry {$moduleEntry};";
            $entries[] = "    &{$moduleEntry},";
        }
        if ($registerProject) {
            $namespace = 'typephp_project_' . $targetName;
            $moduleEntry = 'typephp_' . $targetName . '_module_entry';
            $declarations[] = "namespace {$namespace} { extern zend_module_entry {$moduleEntry}; }";
            $entries[] = "    &{$namespace}::{$moduleEntry},";
        }

        $count = count($entries);
        $storageSize = max(1, $count);
        $contents = "#include <php_nano_extension.h>\n\n"
            . implode("\n", $declarations) . "\n\n"
            . "extern \"C\" zend_result php_nano_startup_composer_extensions() {\n"
            . "    static zend_module_entry *extensions[{$storageSize}] = {\n"
            . implode("\n", $entries) . "\n"
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
