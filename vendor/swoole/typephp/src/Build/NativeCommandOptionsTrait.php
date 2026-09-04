<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Metadata\Constants;

trait NativeCommandOptionsTrait
{
    /** @var null|array{header: string, artifact: string} */
    protected ?array $precompiledHeader = null;

    protected function getCommonCompileCommandOptions(): CompileOptions
    {
        $includePaths = $this->getIncludePaths();
        if (!empty($this->userIncludePaths)) {
            $includePaths = array_merge($includePaths, $this->userIncludePaths);
        }

        $userDefines = $this->userDefines;
        if ($this->isBuildModeLib()) {
            $userDefines[] = 'TYPEPHP_NO_MAIN=1';
            $userDefines[] = $this->getLibraryExportsMacroName() . '=1';
        }

        $values = [
            'include_paths' => $includePaths,
            'optimize' => $this->optimizeLevel,
            'debug' => $this->debug,
            'sanitize' => $this->sanitize,
            'march' => $this->march,
            'target_platform' => $this->targetPlatform,
            'is_zts' => $this->isPhpZts,
            'build_mode' => $this->buildMode,
            'enable_profiler' => $this->enableProfiler,
            'prof_output' => $this->targetName . '.prof',
            'user_defines' => $userDefines,
            'lto' => $this->enableLto,
        ];

        if ($this->debug && $this->isWindows()) {
            $values['compiler_pdb'] = $this->getMsvcCompilerPdbFile();
        }

        return new CompileOptions($values);
    }

    protected function getMsvcCompilerPdbFile(): string
    {
        $directory = $this->getBuildDir() . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'msvc';
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create MSVC PDB directory: ' . $directory);
        }
        return $directory . DIRECTORY_SEPARATOR . $this->targetName . '.compile.pdb';
    }

    protected function getCompileCommandOptions(): CompileOptions
    {
        $options = $this->getCommonCompileCommandOptions();
        $options = $options
            ->with('cpp_std', $this->cxxStd)
            ->with('cxxflags', $this->cxxFlags)
            ->with('suppressed_warnings', Constants::MSVC_SUPPRESSED_WARNINGS ?? []);

        if ($this->isBuildModeLib()) {
            $options = $options->with(
                'forced_include',
                $this->getIncludeDir() . '/php_' . $this->targetName . '_func_decl.h'
            );
        }

        if ($this->precompiledHeader !== null) {
            $options = $options->with('precompiled_header', $this->precompiledHeader);
        }

        return $options;
    }

    protected function getCCompileCommandOptions(): CompileOptions
    {
        $options = $this->getCommonCompileCommandOptions();
        return $options->with('suppressed_warnings', ['4244', '4146']);
    }

    protected function getPrecompiledHeaderCompileCommandOptions(): CompileOptions
    {
        $values = $this->getCompileCommandOptions()->toArray();
        unset($values['forced_include'], $values['precompiled_header']);
        return new CompileOptions($values);
    }

    protected function getNativeCompileCommandOptions(string $language = ''): CompileOptions
    {
        $options = $this->getCommonCompileCommandOptions();
        $options = $options->with('suppressed_warnings', Constants::MSVC_SUPPRESSED_WARNINGS ?? []);

        if ($language === 'objective-c++') {
            $options = $options->with('cpp_std', $this->cxxStd)->with('cxxflags', $this->cxxFlags);
        }

        return $options;
    }

    protected function getProjectRuntimeEntryCompileCommandOptions(): CompileOptions
    {
        $values = $this->getCompileCommandOptions()->toArray();
        // TYPEPHP_PROJECT_NAME is deliberately confined to the small,
        // project-specific entry translation unit. Defining it while loading
        // the common PCH would make every output target require a distinct PCH.
        unset($values['forced_include'], $values['precompiled_header']);
        $values['user_defines'][] = 'TYPEPHP_RUNTIME_EXPORTS=1';
        $values['user_defines'][] = 'TYPEPHP_PROJECT_NAME=' . $this->targetName;
        return new CompileOptions($values);
    }

    protected function getLinkCommandOptions(): LinkOptions
    {
        $libraryPaths = array_merge($this->getLibraryPaths(), $this->linkPaths);
        $libraries = $this->getLibraries();
        if ($this->enableProfiler) {
            $libraries[] = 'profiler';
        }
        $libraries = array_merge($libraries, $this->linkLibs);

        $ldflags = $this->ldflags;
        $targetPlatform = $this->targetPlatform;
        if ($this->fullStatic) {
            // The bundled libphp.a carries musl libc, so the link must use musl's
            // C runtime instead of glibc's. -B points the driver at the musl
            // startup files; -static keeps the result free of NEEDED entries.
            // Only the link step switches target: the translation units are still
            // compiled against the host libstdc++ headers, which is fine because
            // TLS relocations are resolved here rather than at compile time.
            $targetPlatform = $this->getFullStaticTargetTriple();
            $ldflags = trim('-static -B ' . escapeshellarg($this->getFullStaticMuslDir()) . ' ' . $ldflags);
        }

        $options = [
            'library_paths' => $libraryPaths,
            'libraries' => $libraries,
            'ldflags' => $ldflags,
            'debug' => $this->debug,
            'no_console' => $this->noConsole,
            'build_mode' => $this->buildMode,
            'sanitize' => $this->sanitize,
            'lto' => $this->enableLto,
            'target_platform' => $targetPlatform,
        ];

        $rpaths = $this->getPlatform()->getDefaultRpaths($this->getPhpxDir(), $this->getPhpDir());
        if (!empty($rpaths)) {
            $options['rpath'] = $rpaths;
        }

        return new LinkOptions($options);
    }
}
