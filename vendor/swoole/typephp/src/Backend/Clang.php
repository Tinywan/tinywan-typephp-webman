<?php

namespace TypePhp\Backend;

use TypePhp\Platform\PlatformBase;
use TypePhp\Platform\Windows;
use TypePhp\Platform\Macos;

/**
 * Clang compiler backend implementation.
 */
class Clang extends GccLikeBackend
{
    public function __construct(PlatformBase $platform, string $compilerCommand = 'clang++', ?string $linkerCommand = null)
    {
        parent::__construct($platform, $compilerCommand, $linkerCommand);
    }

    public function getName(): string
    {
        return 'Clang';
    }

    public function getLinkerCommand(): string
    {
        if ($this->linkerCommand !== null) {
            return $this->linkerCommand;
        }

        if ($this->platform instanceof Windows) {
            return 'link';
        }
        return $this->compilerCommand;
    }

    /**
     * On Windows, prefer lld-link; fall back to link.exe if it is not available.
     */
    public static function detectWindowsLinker(): string
    {
        $output = [];
        $returnCode = 0;
        exec('lld-link --version 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            return 'lld-link';
        }

        $llvmHome = getenv('LLVM_HOME');
        if ($llvmHome && is_dir($llvmHome)) {
            $lldLinkPath = rtrim($llvmHome, '\/') . '\x64\bin\lld-link.exe';
            if (file_exists($lldLinkPath)) {
                exec('"' . $lldLinkPath . '" --version 2>&1', $output, $returnCode);
                if ($returnCode === 0) {
                    $lldDir = dirname($lldLinkPath);
                    putenv("PATH={$lldDir};" . getenv('PATH'));
                    return 'lld-link';
                }
            }
        }

        return 'link';
    }

    // ──── Hook method overrides ────

    protected function getCompilerPrefixFlags(): string
    {
        if ($this->platform instanceof Windows) {
            return ' -fms-compatibility'
                . ' -fms-compatibility-version=19.40'
                . ' -fdelayed-template-parsing'
                . ' -fms-extensions';
        }
        return '';
    }

    protected function getLinkerOutputFlag(): string
    {
        return $this->platform instanceof Windows ? '/OUT:' : '-o';
    }

    protected function formatSanitizerFlag(string $sanitizer): string
    {
        return '-fsanitize=' . $sanitizer;
    }

    public function getPrecompiledHeaderArtifact(string $headerFile): string
    {
        return dirname($headerFile) . DIRECTORY_SEPARATOR . pathinfo($headerFile, PATHINFO_FILENAME) . '.pch';
    }

    protected function formatPrecompiledHeaderFlag(array $precompiledHeader): string
    {
        return ' -include-pch ' . escapeshellarg($precompiledHeader['artifact']);
    }

    protected function getPICFlag(array $config): string
    {
        if ($this->platform instanceof Windows) {
            return '';
        }
        if ((!empty($config['build_mode']) && ($config['build_mode'] === 'ext' || $config['build_mode'] === 'lib')) || !empty($config['pic'])) {
            return ' -fPIC';
        }
        return '';
    }

    protected function getPlatformLinkFlags(array $config): string
    {
        if ($this->platform instanceof Windows) {
            $flags = '';
            if (!empty($config['debug'])) {
                $flags .= ' /DEBUG';
            }
            if (!empty($config['no_console'])) {
                $flags .= ' ' . $this->platform->getSubsystemOptions(true);
            }
            $flags .= ' ' . $this->platform->getCrtConfig();

            if (!empty($config['build_mode']) && ($config['build_mode'] === 'ext' || $config['build_mode'] === 'lib')) {
                $flags .= ' /DLL';
            }
            return $flags;
        }

        return parent::getPlatformLinkFlags($config);
    }

}
