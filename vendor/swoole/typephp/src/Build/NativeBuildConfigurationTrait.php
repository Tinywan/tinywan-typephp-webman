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
    protected function getIncludePaths(): array
    {
        $platform = $this->getPlatform();
        $includePaths = [
            $this->getPhpxDir() . '/include',
            $this->getBuildDir() . '/include',
            $this->getPhpxDir() . '/src/misc',
        ];

        // 根据平台添加 PHP 包含路径
        if ($platform instanceof Windows) {
            $phpSdkPaths = $platform->buildPhpSdkIncludePaths($this->getPhpDir());
            $includePaths = array_merge($includePaths, $phpSdkPaths);
        } else {
            // Linux/macOS
            $phpPaths = $platform->buildPhpIncludePaths($this->getPhpDir());
            $includePaths = array_merge($includePaths, $phpPaths);
            // 内置 mpdecimal 头文件目录
            $includePaths[] = $this->getPhpxDir() . '/thirdparty/mpdecimal/libmpdec';
            $includePaths[] = $this->getPhpxDir() . '/thirdparty/mpdecimal/libmpdec++';
        }

        return $includePaths;
    }

    protected function getLibraryPaths(): array
    {
        $platform = $this->getPlatform();
        $libraryPaths = [
            $this->getPhpxDir() . '/lib',
        ];

        // 根据平台添加 PHP 库路径
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
     * 获取库文件
     */
    protected function getLibraries(): array
    {
        $platform = $this->getPlatform();
        $libraries = [];

        // phpx 库（根据平台使用不同的文件名格式）
        $phpxLibPath = $this->findPhpxLibrary();
        if ($phpxLibPath === null) {
            $this->error($this->getPhpxLibraryErrorMessage());
        }
        $libraries[] = $phpxLibPath;

        // extension 和 bin 模式都需要链接 PHP 库
        if ($platform instanceof Windows) {
            // Windows: 根据构建模式选择不同的库
            if ($this->isBuildModeEmbed()) {
                // bin 模式：需要同时链接 php8ts.lib 和 php8embed.lib
                // 注意：php8ts.lib 必须在 php8embed.lib 之前，因为 embed 依赖 core
                // php8ts.lib 提供 PHP 核心全局符号（executor_globals, compiler_globals, sapi_globals）
                if (!empty($this->windowsPhpCoreLib)) {
                    $libraries[] = $this->windowsPhpCoreLib;  // 不添加引号
                }
                // php8embed.lib 提供嵌入 API
                if (!empty($this->windowsPhpEmbedLib)) {
                    $libraries[] = $this->windowsPhpEmbedLib;  // 不添加引号
                }
            } else {
                // ext 模式：只使用 php8ts.lib 或 php8.lib（PHP 扩展）
                if (!empty($this->windowsPhpCoreLib)) {
                    $libraries[] = $this->windowsPhpCoreLib;  // 不添加引号
                }
            }
            
            // 添加 Windows API 库（Win32 GUI 程序需要）
            $libraries[] = 'user32.lib';   // Windows UI 函数（CreateWindow, MessageBox 等）
            $libraries[] = 'gdi32.lib';    // GDI 图形函数
            $libraries[] = 'kernel32.lib'; // 核心 Windows API
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
        }

        return $libraries;
    }

    /**
     * 解析 phpx 库文件路径，库不存在时返回 null。
     *
     * Windows 使用 phpx.lib（无 lib 前缀）；其他平台优先使用共享库
     * （libphpx.so / libphpx.dylib），找不到时回退到静态库 libphpx.a。
     */
    protected function findPhpxLibrary(): ?string
    {
        $platform = $this->getPlatform();

        if ($platform instanceof Windows) {
            $phpxLibPath = $this->getPhpxDir() . '\\lib\\phpx.lib';
            return is_file($phpxLibPath) ? $phpxLibPath : null;
        }

        // Linux/macOS：共享库优先，静态库兜底
        // getSharedLibraryExtension() 返回的值可能带点或不带点，需要统一处理
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
     * 生成 phpx 库缺失时的错误信息
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
     * 前置检测 phpx 库是否可用，在编译开始前报错，
     * 避免所有源文件编译完成后才在链接阶段失败。
     */
    protected function validatePhpxLibrary(): void
    {
        if ($this->findPhpxLibrary() === null) {
            $this->error($this->getPhpxLibraryErrorMessage());
        }
    }

}
