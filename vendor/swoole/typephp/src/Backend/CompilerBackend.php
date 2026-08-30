<?php

namespace TypePhp\Backend;

use TypePhp\Platform\PlatformBase;

/**
 * 编译器后端抽象基类
 * 定义所有编译器必须实现的接口
 */
abstract class CompilerBackend
{
    /**
     * 平台实例
     */
    protected PlatformBase $platform;

    /**
     * 最近创建的 Response File 路径，用于构建完成后清理
     */
    protected string $lastResponseFile = '';

    public function __construct(PlatformBase $platform)
    {
        $this->platform = $platform;
    }

    /**
     * 获取编译器名称
     */
    abstract public function getName(): string;

    /**
     * 获取编译器命令
     */
    abstract public function getCompilerCommand(): string;

    /**
     * 获取链接器命令
     */
    abstract public function getLinkerCommand(): string;

    public function supportsPrecompiledHeaders(): bool
    {
        return false;
    }

    public function getPrecompiledHeaderArtifact(string $headerFile): string
    {
        throw new \LogicException($this->getName() . ' does not support precompiled headers');
    }

    /**
     * 构建完整的编译命令
     */
    abstract public function buildCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * 构建 C 文件的编译命令（不包含 C++ 特定选项）
     */
    abstract public function buildCCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * 构建原生源文件的编译命令（汇编/Objective-C 等，使用 -x 指定语言）
     *
     * @param string $language GCC/Clang 语言标识（assembler, objective-c, objective-c++ 等）
     */
    abstract public function buildNativeCompileCommand(
        string $sourceFile,
        string $outputFile,
        array $options = [],
        string $language = ''
    ): string;

    /**
     * 构建完整的链接命令
     */
    abstract public function buildLinkCommand(
        array $objectFiles,
        string $outputFile,
        array $options = []
    ): string;

    /**
     * 构建编译选项（不含文件路径）
     * @param array $config 编译配置
     *   - optimize: 优化级别 (0-3)
     *   - debug_info: 是否生成调试信息
     *   - sanitize: sanitizer 类型 (address, undefined, etc.)
     *   - cpp_std: C++ 标准版本
     *   - is_zts: 是否为 ZTS 模式
     *   - build_mode: 构建模式 ('bin' or 'ext')
     *   - enable_profiler: 是否启用性能分析
     *   - suppressed_warnings: 需要屏蔽的警告代码数组
     *   - cxxflags: 用户自定义编译标志
     *   - compiler_pdb: MSVC 编译器 PDB 输出路径
     */
    abstract public function buildCompileOptions(array $config = []): string;

    /**
     * 构建链接选项（不含文件路径）
     * @param array $config 链接配置
     *   - debug_info: 是否生成调试信息
     *   - no_console: 是否隐藏控制台窗口
     *   - build_mode: 构建模式 ('bin' or 'ext')
     *   - sanitize: sanitizer 类型
     */
    abstract public function buildLinkOptions(array $config = []): string;

    /**
     * 获取平台实例
     */
    public function getPlatform(): PlatformBase
    {
        return $this->platform;
    }

    /**
     * 格式化包含路径
     */
    protected function formatIncludePaths(array $includePaths): string
    {
        return $this->platform->getIncludeFlags($includePaths);
    }

    /**
     * 格式化库路径
     */
    protected function formatLibraryPaths(array $libraryPaths): string
    {
        return $this->platform->getLibraryPathFlags($libraryPaths);
    }

    /**
     * 格式化库文件
     */
    protected function formatLibraries(array $libraries): string
    {
        return $this->platform->getLibraryFlags($libraries);
    }

    protected function formatDefineFlag(string $define, string $prefix): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(=(?:[A-Za-z0-9_.,:+\/@%-]+))?$/', $define) === 1) {
            return $prefix . $define;
        }

        return $prefix . escapeshellarg($define);
    }

    /**
     * 将目标文件列表写入 Response File，避免命令行参数过长超出 OS 限制（Windows 8191 字符）
     *
     * @param array  $objectFiles 目标文件路径列表
     * @param string $targetFile  最终输出文件路径（Response File 写入同目录）
     * @return string 链接器参数，如 @build/project.rsp
     */
    protected function createResponseFile(array $objectFiles, string $targetFile): string
    {
        $rspFile = dirname($targetFile) . DIRECTORY_SEPARATOR . basename($targetFile) . '.rsp';
        $this->lastResponseFile = $rspFile;
        $lines = [];
        foreach ($objectFiles as $file) {
            // 路径含空格时用双引号包裹，MSVC link.exe 和 GCC/Clang 均支持
            if (str_contains($file, ' ')) {
                $file = '"' . $file . '"';
            }
            $lines[] = $file;
        }
        file_put_contents($rspFile, implode("\n", $lines));
        return escapeshellarg('@' . $rspFile);
    }

    /**
     * 删除最近创建的 Response File 临时文件
     */
    public function cleanupResponseFile(): void
    {
        if ($this->lastResponseFile !== '' && file_exists($this->lastResponseFile)) {
            unlink($this->lastResponseFile);
        }
    }
}
