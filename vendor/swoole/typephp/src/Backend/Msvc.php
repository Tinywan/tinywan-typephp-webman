<?php

namespace TypePhp\Backend;

use TypePhp\Platform\Windows;

/**
 * MSVC compiler backend implementation.
 */
class Msvc extends CompilerBackend
{
    private string $compilerCommand;
    private string $linkerCommand;

    public function __construct(Windows $platform, string $compilerCommand = 'cl', string $linkerCommand = 'link')
    {
        parent::__construct($platform);
        $this->compilerCommand = $compilerCommand;
        $this->linkerCommand = $linkerCommand;
    }

    public function getName(): string
    {
        return 'MSVC';
    }

    public function getCompilerCommand(): string
    {
        return $this->compilerCommand;
    }

    public function getLinkerCommand(): string
    {
        return $this->linkerCommand;
    }

    private function buildCommonCompileFlags(array $config, bool $includeCppOptions = true): string
    {
        $cmd = '';

        $cmd .= ' /utf-8 /DZEND_WIN32 /DPHP_WIN32 /DZEND_DEBUG=0 /DENABLE_INTSAFE_SIGNED_FUNCTIONS';

        if (!empty($config['is_zts'])) {
            $cmd .= ' /DZTS';
        }

        if (!empty($config['sanitize'])) {
            if ($config['sanitize'] === 'address' || $config['sanitize'] === 'addr') {
                $cmd .= ' /fsanitize=address';
            }
        }

        if (!empty($config['debug'])) {
            $cmd .= ' /Od /Zi';
            if (!empty($config['compiler_pdb'])) {
                $cmd .= ' /Fd' . escapeshellarg($config['compiler_pdb']);
                // All translation units of one target share this compiler PDB.
                // Serialize writes within the target while /Fd isolates apps.
                $cmd .= ' /FS';
            }
        } else {
            $optimizeLevel = $config['optimize'] ?? 2;
            $optMap = [0 => '/Od', 1 => '/O1', 2 => '/O2', 3 => '/Ox'];
            $cmd .= ' ' . ($optMap[$optimizeLevel] ?? '/O2');
        }

        $cmd .= ' /W3';

        if (!empty($config['suppressed_warnings'])) {
            foreach ($config['suppressed_warnings'] as $code => $description) {
                $code = is_int($code) && $code < 100 ? $description : $code;
                $cmd .= " /wd{$code}";
            }
        }

        if (!empty($config['enable_profiler'])) {
            $cmd .= ' ' . $this->formatDefineFlag('PPROF_ON=1', '/D');
            if (!empty($config['prof_output'])) {
                $profOutput = addcslashes($config['prof_output'], "\\\"");
                $cmd .= ' ' . $this->formatDefineFlag('PROF_OUTPUT_FILE="' . $profOutput . '"', '/D');
            }
        }

        if (!empty($config['user_defines'])) {
            foreach ($config['user_defines'] as $define) {
                $cmd .= ' ' . $this->formatDefineFlag($define, '/D');
            }
        }

        if (!empty($config['lto'])) {
            $cmd .= ' /GL';
        }

        if ($includeCppOptions) {
            $cmd .= ' /EHsc';
            if (!empty($config['cpp_std'])) {
                $cmd .= ' /std:' . $config['cpp_std'];
            }

            $cmd .= ' /MD';

            if (!empty($config['cxxflags'])) {
                $cmd .= ' ' . $config['cxxflags'];
            }

            if (!empty($config['forced_include'])) {
                $cmd .= ' /FI' . escapeshellarg($config['forced_include']);
            }
        }

        $cmd .= ' /nologo';

        return $cmd;
    }

    public function buildCompileCommand(string $sourceFile, string $outputFile, array $options = []): string
    {
        $cmd = $this->getCompilerCommand();
        $cmd .= ' /c';
        $cmd .= ' ' . escapeshellarg($sourceFile);
        $cmd .= ' /Fo' . escapeshellarg($outputFile);

        if (!empty($options['include_paths'])) {
            $cmd .= ' ' . $this->formatIncludePaths($options['include_paths']);
        }

        $options['is_zts'] ??= $this->platform instanceof Windows && $this->platform->isZts();
        $cmd .= $this->buildCompileOptions($options);

        return $cmd;
    }

    /**
     * Build the compile command for C files (excludes C++-specific options).
     */
    public function buildCCompileCommand(string $sourceFile, string $outputFile, array $options = []): string
    {
        $cmd = $this->getCompilerCommand();
        $cmd .= ' /c';
        $cmd .= ' /TC';
        $cmd .= ' ' . escapeshellarg($sourceFile);
        $cmd .= ' /Fo' . escapeshellarg($outputFile);

        if (!empty($options['include_paths'])) {
            $cmd .= ' ' . $this->formatIncludePaths($options['include_paths']);
        }

        // Platform macro definitions.
        $cmd .= $this->buildCommonCompileFlags($options, false);

        // Note: C files do not use C++-specific options such as /EHsc, /std:c++17, /MD.

        return $cmd;
    }

    /**
     * Build the compile command for native source files.
     *
     * MSVC only supports C files (/TC); assembly and ObjC files are not supported.
     *
     * @param string $language Language identifier.
     */
    public function buildNativeCompileCommand(string $sourceFile, string $outputFile, array $options = [], string $language = ''): string
    {
        if ($language === 'c') {
            return $this->buildCCompileCommand($sourceFile, $outputFile, $options);
        }

        throw new \RuntimeException(
            "MSVC does not support compiling source file of language '{$language}': {$sourceFile}"
        );
    }

    public function buildLinkCommand(array $objectFiles, string $outputFile, array $options = []): string
    {
        $cmd = $this->getLinkerCommand();
        $cmd .= ' ' . $this->createResponseFile($objectFiles, $outputFile);
        $cmd .= ' /OUT:' . escapeshellarg($outputFile);

        if (!empty($options['debug'])) {
            $cmd .= ' /PDB:' . escapeshellarg($this->getLinkPdbFile($outputFile));
        }

        if (!empty($options['library_paths'])) {
            $cmd .= ' ' . $this->formatLibraryPaths($options['library_paths']);
        }

        if (!empty($options['ldflags'])) {
            $cmd .= ' ' . $options['ldflags'];
        }

        $cmd .= $this->buildLinkOptions($options);

        if (!empty($options['libraries'])) {
            $cmd .= ' ' . $this->formatLibraries($options['libraries']);
        }

        return $cmd;
    }

    private function getLinkPdbFile(string $outputFile): string
    {
        $forwardSlash = strrpos($outputFile, '/');
        $backslash = strrpos($outputFile, '\\');
        $lastSlash = max(
            $forwardSlash === false ? -1 : $forwardSlash,
            $backslash === false ? -1 : $backslash,
        );
        $lastDot = strrpos($outputFile, '.');
        $base = $lastDot !== false && $lastDot > $lastSlash
            ? substr($outputFile, 0, $lastDot)
            : $outputFile;
        return $base . '.pdb';
    }

    /**
     * Build compile options (implements the abstract method).
     */
    public function buildCompileOptions(array $config = []): string
    {
        return $this->buildCommonCompileFlags($config, true);
    }

    /**
     * Build link options (implements the abstract method).
     */
    public function buildLinkOptions(array $config = []): string
    {
        $cmd = '';

        // Debug.
        if (!empty($config['debug'])) {
            $cmd .= ' /DEBUG';
        }

        // Windows subsystem.
        if (!empty($config['no_console'])) {
            $cmd .= ' ' . $this->platform->getSubsystemOptions(true);
        }

        // CRT configuration.
        $cmd .= ' ' . $this->platform->getCrtConfig();

        // Extension module options.
        if (!empty($config['build_mode']) && ($config['build_mode'] === 'ext' || $config['build_mode'] === 'lib')) {
            $cmd .= ' /DLL';
        }

        // nologo.
        $cmd .= ' /nologo';

        // LTO (Link Time Code Generation).
        if (!empty($config['lto'])) {
            $cmd .= ' /LTCG';
        }

        return $cmd;
    }

    /**
     * Compile a Windows resource file (.rc) into an object file (.res).
     *
     * Uses rc.exe (the MSVC resource compiler) to compile a .rc file into a .res file.
     * The .res file can be passed directly to link.exe as input.
     *
     * @param string $rcFile  Resource file path (.rc).
     * @param string $resFile Output resource file path (.res).
     * @return string The compile command.
     */
    public function compileResourceFile(string $rcFile, string $resFile): string
    {
        // rc.exe is the resource compiler bundled with MSVC.
        // /nologo: suppress the copyright banner.
        // /fo: specify the output file.
        $cmd = 'rc.exe /nologo';
        $cmd .= ' /fo ' . escapeshellarg($resFile);
        $cmd .= ' ' . escapeshellarg($rcFile);

        return $cmd;
    }
}
