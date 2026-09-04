<?php
/**
 * This file is part of TypePHP.
 *
 * Resolves native include, library, linker, and output configuration.
 */

namespace TypePhp\Build;

use TypePhp\Platform\Windows;

trait NativeBuildConfigurationTrait
{
    /**
     * Resolve the fully-static SDK directory (phpx/full-static/sdk).
     *
     * Fully-static builds are self-contained: the SDK bundles libphp.a and
     * libphpx.a plus every required header, so neither PHP_HOME nor php-config
     * is consulted. Returns null when fully-static mode is disabled.
     */
    protected function getFullStaticSdkDir(): ?string
    {
        if (!$this->fullStatic) {
            return null;
        }
        $sdkDir = $this->getPhpxDir() . '/full-static/sdk';
        if (!is_dir($sdkDir)) {
            $this->error(
                '--full-static requires the bundled SDK at phpx/full-static/sdk; not found at: ' . $sdkDir
            );
        }
        return $sdkDir;
    }

    /**
     * The target triple used for fully-static links.
     *
     * libphp.a embeds musl libc, so the executable must be linked as a musl
     * target; the triple also drives the linker's search for musl startup files.
     */
    protected function getFullStaticTargetTriple(): string
    {
        $arch = match (php_uname('m')) {
            'aarch64', 'arm64' => 'aarch64',
            'riscv64' => 'riscv64',
            default => 'x86_64',
        };
        return $arch . '-unknown-linux-musl';
    }

    /**
     * Resolve the directory holding the musl startup files (crt1.o/crti.o/crtn.o).
     *
     * A fully-static link must not pull crt1.o from glibc: glibc's _start lets
     * musl's __libc_start_main (bundled in libphp.a) install a thread pointer
     * whose layout does not match the TLS offsets the linker computed, so every
     * thread-local read (ZTS globals, and PHP's ZEND_TLS data) lands on the
     * wrong address and the process crashes during php_module_startup.
     *
     * The startup files ship with the SDK, at phpx/full-static/sdk/lib/musl.
     */
    protected function getFullStaticMuslDir(): string
    {
        $sdkDir = $this->getFullStaticSdkDir();
        $muslDir = $sdkDir . '/lib/musl';
        if (!is_file($muslDir . '/crt1.o')) {
            $this->error(
                "--full-static requires the musl startup files bundled with the SDK;\n"
                . "  crt1.o not found at: {$muslDir}\n"
                . '  Rebuild the SDK with sapi/scripts/build-sdk.sh, which now copies them there.'
            );
        }
        return $muslDir;
    }

    protected function getIncludePaths(): array
    {
        $sdkDir = $this->getFullStaticSdkDir();
        if ($sdkDir !== null) {
            return [
                $sdkDir . '/include/phpx',
                $sdkDir . '/include',
                $sdkDir . '/include/php',
                $sdkDir . '/include/php/main',
                $sdkDir . '/include/php/Zend',
                $sdkDir . '/include/php/TSRM',
                $sdkDir . '/include/php/ext',
                $sdkDir . '/include/php/ext/date/lib',
                $this->getBuildDir() . '/include',
                $this->getPhpxDir() . '/src/misc',
            ];
        }

        $platform = $this->getPlatform();
        $includePaths = [
            $this->getPhpxDir() . '/include',
            $this->getBuildDir() . '/include',
            $this->getPhpxDir() . '/src/misc',
        ];

        // Add the platform-specific PHP include paths
        if ($platform instanceof Windows) {
            $phpSdkPaths = $platform->buildPhpSdkIncludePaths($this->getPhpDir());
            $includePaths = array_merge($includePaths, $phpSdkPaths);
        } else {
            // Linux/macOS
            $phpPaths = $platform->buildPhpIncludePaths($this->getPhpDir());
            $includePaths = array_merge($includePaths, $phpPaths);
            // Bundled mpdecimal header directories
            $includePaths[] = $this->getPhpxDir() . '/thirdparty/mpdecimal/libmpdec';
            $includePaths[] = $this->getPhpxDir() . '/thirdparty/mpdecimal/libmpdec++';
        }

        return $includePaths;
    }

    protected function getLibraryPaths(): array
    {
        $sdkDir = $this->getFullStaticSdkDir();
        if ($sdkDir !== null) {
            return [$sdkDir . '/lib'];
        }

        $platform = $this->getPlatform();
        $libraryPaths = [
            $this->getPhpxDir() . '/lib',
        ];

        // Add the platform-specific PHP library paths
        if ($platform instanceof Windows) {
            $phpLibPaths = $platform->buildPhpSdkLibPaths($this->getPhpDir());
            $libraryPaths = array_merge($libraryPaths, $phpLibPaths);
        } else {
            // Linux/macOS
            $phpLibPaths = $platform->buildPhpLibPaths($this->getPhpDir());
            $libraryPaths = array_merge($libraryPaths, $phpLibPaths);
        }

        return $libraryPaths;
    }

    /**
     * Get the library files to link against
     */
    protected function getLibraries(): array
    {
        $sdkDir = $this->getFullStaticSdkDir();
        if ($sdkDir !== null) {
            // Fully-static: both archives are self-contained. libphpx.a comes
            // first because it references symbols resolved by libphp.a; no
            // -lgmp/-lgmpxx/-lmpfr or system libc is needed.
            return [
                $sdkDir . '/lib/libphpx.a',
                $sdkDir . '/lib/libphp.a',
            ];
        }

        $platform = $this->getPlatform();
        $libraries = [];

        // phpx library (file name format differs by platform)
        $phpxLibPath = $this->findPhpxLibrary();
        if ($phpxLibPath === null) {
            $this->error($this->getPhpxLibraryErrorMessage());
        }
        $libraries[] = $phpxLibPath;

        // Both extension and bin modes need to link the PHP library
        if ($platform instanceof Windows) {
            // Windows: pick different libraries based on the build mode
            if ($this->isBuildModeEmbed()) {
                // bin mode: link both php8ts.lib and php8embed.lib
                // Note: php8ts.lib must come before php8embed.lib because embed depends on core
                // php8ts.lib provides the PHP core global symbols (executor_globals, compiler_globals, sapi_globals)
                if (!empty($this->windowsPhpCoreLib)) {
                    $libraries[] = $this->windowsPhpCoreLib;  // do not quote
                }
                // php8embed.lib provides the embed API
                if (!empty($this->windowsPhpEmbedLib)) {
                    $libraries[] = $this->windowsPhpEmbedLib;  // do not quote
                }
            } else {
                // ext mode: use only php8ts.lib or php8.lib (PHP extension)
                if (!empty($this->windowsPhpCoreLib)) {
                    $libraries[] = $this->windowsPhpCoreLib;  // do not quote
                }
            }
            
            // Add the Windows API libraries (required by Win32 GUI programs)
            $libraries[] = 'user32.lib';   // Windows UI functions (CreateWindow, MessageBox, etc.)
            $libraries[] = 'gdi32.lib';    // GDI graphics functions
            $libraries[] = 'kernel32.lib'; // Core Windows API
            $libraries[] = 'gmp.lib';
            $libraries[] = 'gmpxx.lib';
            $libraries[] = 'mpfr.lib';
            $libraries[] = 'libmpdec-4.0.1.dll.lib';
            $libraries[] = 'libmpdec++-4.0.1.dll.lib';
        } else {
            // Unix PHP extensions resolve Zend/PHP symbols from the host SAPI.
            // Linking libphp.so here would load a second ZendVM and give PHPX a
            // different set of compiler/executor globals from the host process.
            if (!$this->isBuildModeExt()) {
                $libraries[] = 'php';
            }
            $libraries[] = 'gmp';
            $libraries[] = 'gmpxx';
            $libraries[] = 'mpfr';
            // The C++ runtime has to be requested explicitly: libphp.so carries
            // none, and a C driver (clang) does not add it the way g++/clang++
            // do. WASI needs nothing — its toolchain supplies the runtime.
            if ($this->isLinux()) {
                $libraries[] = 'stdc++';
            } elseif ($this->isMacos()) {
                $libraries[] = 'c++';
            }
        }

        return $libraries;
    }

    /**
     * Resolve the phpx library file path, returning null when the library does
     * not exist.
     *
     * Windows uses phpx.lib (no lib prefix); other platforms prefer the shared
     * library (libphpx.so / libphpx.dylib) and fall back to the static library
     * libphpx.a when it is not found.
     */
    protected function findPhpxLibrary(): ?string
    {
        $sdkDir = $this->getFullStaticSdkDir();
        if ($sdkDir !== null) {
            $phpxStaticPath = $sdkDir . '/lib/libphpx.a';
            return is_file($phpxStaticPath) ? $phpxStaticPath : null;
        }

        $platform = $this->getPlatform();

        if ($platform instanceof Windows) {
            $phpxLibPath = $this->getPhpxDir() . '\\lib\\phpx.lib';
            return is_file($phpxLibPath) ? $phpxLibPath : null;
        }

        // Linux/macOS: prefer the shared library, fall back to the static library
        // getSharedLibraryExtension() may or may not include a leading dot, so normalize it
        $sharedLibExt = ltrim($platform->getSharedLibraryExtension(), '.');
        $phpxLibPath = $this->getPhpxDir() . '/lib/libphpx.' . $sharedLibExt;
        if (is_file($phpxLibPath)) {
            return $phpxLibPath;
        }

        // Stateful PHPX runtime facilities (global Zend handlers and internal
        // classes) must have one process-wide owner when a native module is
        // loaded into another process. Statically linking PHPX into each
        // extension/library would duplicate that state.
        if (!$this->isWasiTarget() && ($this->isBuildModeExt() || $this->isBuildModeLib())) {
            return null;
        }

        $phpxStaticPath = $this->getPhpxDir() . '/lib/libphpx.a';
        return is_file($phpxStaticPath) ? $phpxStaticPath : null;
    }

    /**
     * Generate the error message shown when the phpx library is missing
     */
    protected function getPhpxLibraryErrorMessage(): string
    {
        $platform = $this->getPlatform();
        if ($platform instanceof Windows) {
            $expected = $this->getPhpxDir() . '\\lib\\phpx.lib';
            $buildHint = 'Build PHPX first (for example, run `nmake phpx` in ' . $this->getPhpxDir() . '\\build)';
        } else {
            $sharedLibExt = ltrim($platform->getSharedLibraryExtension(), '.');
            $expected = $this->getPhpxDir() . '/lib/libphpx.' . $sharedLibExt;
            if ($this->isWasiTarget() || (!$this->isBuildModeExt() && !$this->isBuildModeLib())) {
                $expected .= ' or ' . $this->getPhpxDir() . '/lib/libphpx.a';
            }
            $buildHint = 'Build phpx first (e.g. run `cmake --build ' . $this->getPhpxDir() . '/build`)';
        }

        return 'phpx library not found at: ' . $expected . PHP_EOL .
            $buildHint . PHP_EOL .
            'or set PHPX_HOME to a phpx installation that provides the library.';
    }

    /**
     * Verify the phpx library is available up front and fail before compilation
     * starts, rather than only failing at link time after all source files have
     * been compiled.
     */
    protected function validatePhpxLibrary(): void
    {
        if ($this->findPhpxLibrary() === null) {
            $this->error($this->getPhpxLibraryErrorMessage());
        }
    }

}
