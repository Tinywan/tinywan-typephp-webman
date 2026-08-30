<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Build;

use TypePhp\Backend\CompilerFactory;
use TypePhp\Exception\SyntaxError;
use TypePhp\Exception\Unsupported;
use TypePhp\Installer\LibPhpInstaller;
use TypePhp\Installer\LibPhpxInstaller;
use TypePhp\Platform\Linux;
use TypePhp\Platform\Wasi;
use TypePhp\Platform\Windows;

trait SourcePipelineTrait
{
    public function addFiles(array $files): void
    {
        $this->sourceDirs = array_merge($this->sourceDirs, $files);
    }

    public function getFiles(string $path): array
    {
        $this->applyPhpVersionCommandLineArgument();
        $realpath = realpath($path);
        if ($realpath === false) {
            $this->error("path not exists: {$path}");
        }
        $path = $realpath;

        if (is_dir($path)) {
            // 目录模式：不解析 YAML
            $list = $this->getFilesFromDir($path);
            $targetName = basename($path);
            $this->setTargetName($targetName);
            $this->sourceDirs[] = $path;
        } else {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if ($ext === 'yml' || $ext === 'yaml') {
                // YAML 配置模式：先解析 YAML
                $list = $this->parseProjectYaml($path);
            } elseif ($ext === 'php') {
                // 单文件模式：不解析 YAML
                $list = [$path];
                $targetName = FileScanner::getFileName($path);
                $this->setTargetName($targetName);
                $this->sourceDirs[] = dirname($path);
            } else {
                $this->error('Unsupported file type: ' . $path);
            }
        }

        // 在所有配置加载完成后，应用命令行参数（确保优先级最高）
        $this->applyCommandLineArguments();

        // The generated public import stub is an output artifact, not an input
        // of the library that produced it. Exclude a previous build's copy when
        // a project scans its output directory recursively.
        if ($this->isBuildModeLib()) {
            $generatedStub = realpath($this->getLibraryImportStubFile());
            if ($generatedStub !== false) {
                $list = array_values(array_filter(
                    $list,
                    static fn(string $file): bool => realpath($file) !== $generatedStub,
                ));
            }
        }

        return $this->filterIgnoredFiles($list);
    }

    public function prepare(string $path): array
    {
        $files = $this->getFiles($path);

        if ($this->isBuildModeEmbed() && $this->getPlatform() instanceof Linux) {
            try {
                $phpDir = (new LibPhpInstaller())->ensure($this->getPhpDir()) ?? $this->getPhpDir();
            } catch (\Throwable $e) {
                $this->error('Unable to install libphp.so: ' . $e->getMessage());
            }
        } else {
            $phpDir = $this->getPhpDir();
        }

        if (!($this->getPlatform() instanceof Wasi)) {
            $this->validatePhpRuntimeMinimum($phpDir);
        }

        if ($this->getPlatform() instanceof Linux) {
            try {
                (new LibPhpxInstaller())->ensure($this->getPhpxDir(), $phpDir);
            } catch (\Throwable $e) {
                $this->error('Unable to build libphpx.so: ' . $e->getMessage());
            }
        }

        // 仅在 PHP 脚本入口（bin/tpc.php）前置检测 phpx 库：缺少库立即 fatal，
        // 避免继续向下执行到文件处理/编译阶段才报错。已编译的 tpc 可执行文件
        // 在进入 main() 前就由动态链接器加载 libphpx，无需（也无法）在此检测。
        if (defined('TYPEPHP_PHP_SCRIPT_ENTRY') && !($this->getPlatform() instanceof Wasi)) {
            $this->validatePhpxLibrary();
        }

        $this->validateCompilerToolchain();

        // shell_exec 和 define 已通过 php::fn:: 直接调用，无需动态符号表

        // Windows 的所有构建模式都依赖 PHPX 导入库和运行库。
        // 其他平台仅在嵌入式构建模式下执行现有检查。
        if ($this->isBuildModeEmbed() || $this->getPlatform() instanceof Windows) {
            foreach ($this->getPlatform()->getBuildLibraryWarnings(
                $this->getPhpDir(),
                $this->getPhpxDir(),
                $this->buildMode,
                defined('TYPEPHP_PHP_SCRIPT_ENTRY'),
            ) as $message) {
                if (!empty($message['error'])) {
                    $detail = $message['error'];
                    if (!empty($message['info'])) {
                        $detail .= "\n" . $message['info'];
                    }
                    $this->error($detail);
                }
                $this->climate->warning($message['warning']);
                if (!empty($message['info'])) {
                    $this->climate->info($message['info']);
                }
            }
        }

        $files = $this->filterIgnoredFiles($files);
        $this->discoverNativeClassDeclarations($files);
        // 分析 PHP 文件，预处理
        foreach ($files as $k => $file) {
            if (FileScanner::isPhpFile($file)) {
                try {
                    $this->prepareFile($file);
                } catch (Unsupported $e) {
                    $this->output(' unsupported syntax: ' . $e->getMessage() . "\n" . ' skip: ' . $file . "\n", 'error');
                    unset($files[$k]);
                } catch (SyntaxError $e) {
                    $this->output(' syntax error: ' . $e->getMessage() . "\n" . ' skip: ' . $file . "\n", 'error');
                    unset($files[$k]);
                }
            }
        }
        // Global slots are shared by every translation unit. Fix any Native
        // pointer ABI now, after declarations are known and before the first
        // per-file C++ body is generated.
        $this->discoverNativeGlobalObjects(array_values($files));
        $files = $this->getSortedFiles($files);
        return $files;
    }

    protected function validateCompilerToolchain(): void
    {
        $backend = $this->getCompilerBackend();
        $compilerCommand = $backend->getCompilerCommand();
        if (!CompilerFactory::isCommandExecutable($compilerCommand)) {
            $program = CompilerFactory::getCommandProgram($compilerCommand);
            $this->error(
                "C/C++ compiler executable not found: {$program}\n" .
                "Configured compiler command: {$compilerCommand}\n" .
                "Install a supported compiler or set `cpp-compiler` in project.yml / PHPX_CC / CXX."
            );
        }

        $linkerCommand = $backend->getLinkerCommand();
        if ($linkerCommand !== $compilerCommand && !CompilerFactory::isCommandExecutable($linkerCommand)) {
            $program = CompilerFactory::getCommandProgram($linkerCommand);
            $this->error(
                "Linker executable not found: {$program}\n" .
                "Configured linker command: {$linkerCommand}\n" .
                "Install the required linker or update compiler configuration."
            );
        }
    }

    /** Validate the selected headers/libphp independently of --php-version. */
    protected function validatePhpRuntimeMinimum(string $phpDir): void
    {
        $versionId = null;
        $headers = [
            $phpDir . '/include/php/main/php_version.h',
            $phpDir . '/include/main/php_version.h',
        ];
        foreach ($headers as $header) {
            if (!is_file($header)) {
                continue;
            }
            $contents = file_get_contents($header);
            if (is_string($contents) && preg_match('/^#define\s+PHP_VERSION_ID\s+(\d+)/m', $contents, $matches)) {
                $versionId = (int) $matches[1];
                break;
            }
        }

        if ($versionId === null) {
            $phpConfig = $phpDir . '/bin/php-config';
            if (is_executable($phpConfig)) {
                $value = shell_exec(escapeshellarg($phpConfig) . ' --vernum 2>/dev/null');
                if (is_string($value) && ctype_digit(trim($value))) {
                    $versionId = (int) trim($value);
                }
            }
        }

        if ($versionId !== null && $versionId < 80400) {
            $version = intdiv($versionId, 10000) . '.' . intdiv($versionId % 10000, 100);
            $this->error("TypePHP requires libphp 8.4 or later; selected PHP installation is {$version}: {$phpDir}");
        }
    }

    protected function shouldIgnoreFile(string $file): bool
    {
        foreach ($this->ignorePaths as $ignorePath) {
            if ($file === $ignorePath) {
                return true;
            }
            if (is_dir($ignorePath) && str_starts_with($file, rtrim($ignorePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    protected function filterIgnoredFiles(array $files): array
    {
        if (empty($this->ignorePaths)) {
            return $files;
        }

        $filteredFiles = [];
        foreach ($files as $file) {
            if (!$this->shouldIgnoreFile($file)) {
                $filteredFiles[] = $file;
            }
        }

        return $filteredFiles;
    }

    public function convert(array $files): array
    {
        $previousPhase = $this->enterCompilerPhase(self::PHASE_CONVERT);
        try {
            // All declarations are now known. Lower declaration constant
            // expressions before translating any function body so cache IDs
            // are assigned exclusively in the convert phase.
            $this->finalizeDeclarationExpressions($files);

            $sourceFiles = [];
            $validSourceCount = 0;
            // 生成 C++ 文件
            foreach ($files as $k => $file) {
                try {
                    if (FileScanner::isPhpFile($file)) {
                        $cppFile = $this->convertFile($file);
                    } elseif (FileScanner::isNativeSourceFile($file)) {
                        $cppFile = $file;
                    } else {
                        continue;
                    }
                    $validSourceCount++;
                    if ($cppFile !== null) {
                        $sourceFiles[] = $cppFile;
                    }
                } catch (Unsupported $e) {
                    echo ' unsupported syntax: ' . $e->getMessage() . "\n";
                    echo ' skip: ' . $file . "\n";
                    unset($files[$k]);
                }
            }

            // A valid PHP input may intentionally emit no standalone translation
            // unit (for example a compile-time trait or an interface). The shared
            // extension source still carries its runtime metadata, so only reject
            // an input set in which no supported source was converted at all.
            if ($validSourceCount === 0) {
                $this->stop('No valid source file found');
            }

            // A WASI library publishes WIT/Component exports rather than a native
            // TypePHP shared-library ABI, so a PHP import stub would be misleading.
            if ($this->isBuildModeLib() && !$this->isWasiTarget()) {
                $this->genLibraryImportStub($files);
            }

            // 生成构建期内部头文件：函数声明、运行时数据声明
            $this->genFunctionDeclarations($this->getIncludeDir() . "/php_{$this->targetName}_func_decl.h");
            $this->genDataDeclarations($this->getIncludeDir() . "/php_{$this->targetName}_data_decl.h");
            // 生成扩展模块源文件
            $sourceFiles[] = $this->genExtension();

            return $sourceFiles;
        } finally {
            $this->restoreCompilerPhase($previousPhase);
        }
    }
}
