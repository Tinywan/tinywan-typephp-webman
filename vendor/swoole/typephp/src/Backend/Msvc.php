<?php

namespace TypePhp\Backend;

use TypePhp\Platform\Windows;

/**
 * MSVC 编译器后端实现
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
     * 构建 C 文件的编译命令（不包含 C++ 特定选项）
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

        // 平台宏定义
        $cmd .= $this->buildCommonCompileFlags($options, false);

        // 注意：C 文件不使用 /EHsc, /std:c++17, /MD 等 C++ 特定选项

        return $cmd;
    }

    /**
     * 构建原生源文件的编译命令
     *
     * MSVC 仅支持 C 文件（/TC），汇编和 ObjC 文件不受支持
     *
     * @param string $language 语言标识
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
     * 构建编译选项（实现抽象方法）
     */
    public function buildCompileOptions(array $config = []): string
    {
        return $this->buildCommonCompileFlags($config, true);
    }
    
    /**
     * 构建链接选项（实现抽象方法）
     */
    public function buildLinkOptions(array $config = []): string
    {
        $cmd = '';
    
        // 调试
        if (!empty($config['debug'])) {
            $cmd .= ' /DEBUG';
        }

        // Windows 子系统
        if (!empty($config['no_console'])) {
            $cmd .= ' ' . $this->platform->getSubsystemOptions(true);
        }

        // CRT 配置
        $cmd .= ' ' . $this->platform->getCrtConfig();

        // 扩展模块选项
        if (!empty($config['build_mode']) && ($config['build_mode'] === 'ext' || $config['build_mode'] === 'lib')) {
            $cmd .= ' /DLL';
        }

        // nologo
        $cmd .= ' /nologo';

        // LTO（链接时代码生成）
        if (!empty($config['lto'])) {
            $cmd .= ' /LTCG';
        }

        return $cmd;
    }

    /**
     * 编译 Windows 资源文件 (.rc) 为目标文件 (.res)
     *
     * 使用 rc.exe（MSVC 资源编译器）将 .rc 文件编译为 .res 文件
     * .res 文件可以直接传给 link.exe 作为输入
     *
     * @param string $rcFile  资源文件路径 (.rc)
     * @param string $resFile 输出资源文件路径 (.res)
     * @return string 编译命令
     */
    public function compileResourceFile(string $rcFile, string $resFile): string
    {
        // rc.exe 是 MSVC 自带的资源编译器
        // /nologo: 不显示版权信息
        // /fo: 指定输出文件
        $cmd = 'rc.exe /nologo';
        $cmd .= ' /fo ' . escapeshellarg($resFile);
        $cmd .= ' ' . escapeshellarg($rcFile);

        return $cmd;
    }
}
