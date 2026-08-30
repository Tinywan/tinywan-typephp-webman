#!/usr/bin/env php
<?php
/**
 * TypePHP cross-platform release packager.
 *
 * Windows produces a self-contained PHP/PHPX SDK package. Linux contains the
 * tested ELF compiler and production Composer vendor tree, but no host native
 * libraries. All platforms share version, staging, verification, and cleanup.
 */

if (!chdir(__DIR__)) {
    throw new RuntimeException('Unable to enter the project directory: ' . __DIR__);
}

if (in_array('--help', $argv ?? [], true) || in_array('-h', $argv ?? [], true)) {
    echo "Usage: php package.php\n\n";
    echo "Windows: requires PHP_HOME and PHPX_HOME; creates a self-contained SDK package.\n";
    echo "Linux: uses strip; packages the tested ELF and production Composer vendor tree.\n";
    echo "macOS: uses strip; packages the tested Mach-O binary and production Composer vendor tree.\n";
    echo "Supported architectures: 64-bit CPUs, including x64 and ARM64.\n";
    exit(0);
}

if (PHP_OS_FAMILY !== 'Windows') {
    packageUnixLike();
    exit(0);
}

echo "========================================\n";
echo "TypePHP Windows 打包脚本\n";
echo "========================================\n\n";

// ==================== 配置参数 ====================
// 从环境变量读取 PHP 和 phpx 目录
$phpDir = getenv('PHP_HOME');
$phpxDir = getenv('PHPX_HOME');

// 如果环境变量未设置，使用默认值
if (empty($phpDir)) {
    echo "警告: PHP_HOME 环境变量未设置\n";
    echo "请设置 PHP_HOME 指向 PHP 安装目录\n";
    echo "例如: set PHP_HOME=C:\\php\\php-8.4.20\n\n";
    exit(1);
}

if (empty($phpxDir)) {
    echo "警告: PHPX_HOME 环境变量未设置\n";
    echo "请设置 PHPX_HOME 指向 phpx 项目目录\n";
    echo "例如: set PHPX_HOME=D:\\workspace\\phpx\n\n";
    exit(1);
}

echo "PHP 目录: {$phpDir}\n";
echo "phpx 目录: {$phpxDir}\n\n";

$compilerExe = 'tpc.exe';
// ==================== 1. 版本号管理 ====================
echo "[1/7] 处理版本号...\n";

$versionId = resolvePackageVersion();

echo "当前版本: {$versionId}\n\n";

// ==================== 2. 检测系统架构 ====================
echo "[2/7] 检测系统架构...\n";

$processorArchitecture = getenv('PROCESSOR_ARCHITEW6432') ?: getenv('PROCESSOR_ARCHITECTURE');
if (PHP_INT_SIZE !== 8) {
    $detectedArchitecture = $processorArchitecture ?: 'unknown';
    echo "错误: TypePHP 不支持 32 位 Windows 架构 - {$detectedArchitecture}\n";
    exit(1);
}
$arch = normalizeArchitecture((string)$processorArchitecture);

$osType = 'windows';
$outputFile = "tpc_v{$versionId}_{$osType}_{$arch}.zip";

echo "操作系统: {$osType}\n";
echo "硬件架构: {$arch}\n";
echo "输出文件: {$outputFile}\n\n";

// ==================== 3. 检查必要文件 ====================
echo "[3/7] 检查必要文件...\n";

$requiredFiles = [
    $compilerExe,
    'README.md',
    'LICENSE',
    'composer.json',
    'examples/hello.php',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        echo "错误: 文件不存在 - {$file}\n";
        exit(1);
    }
}

$phpEmbedLibCandidates = [
    'SDK/lib/php8embed.lib' => "{$phpDir}/SDK/lib/php8embed.lib",
    'lib/php8embed.lib' => "{$phpDir}/lib/php8embed.lib",
    'php8embed.lib' => "{$phpDir}/php8embed.lib",
];
$phpEmbedLib = null;
$phpEmbedLibRelativePath = null;
foreach ($phpEmbedLibCandidates as $relativePath => $candidate) {
    if (is_file($candidate)) {
        $phpEmbedLib = $candidate;
        $phpEmbedLibRelativePath = $relativePath;
        break;
    }
}
if ($phpEmbedLib === null) {
    echo "错误: 未找到 Windows 嵌入式 PHP 导入库 php8embed.lib\n";
    echo "请先构建或安装包含 php8embed.lib 的 PHP SDK\n";
    exit(1);
}

$phpCoreLibCandidates = [
    'SDK/lib/php8ts.lib' => "{$phpDir}/SDK/lib/php8ts.lib",
    'SDK/lib/php8.lib' => "{$phpDir}/SDK/lib/php8.lib",
    'lib/php8ts.lib' => "{$phpDir}/lib/php8ts.lib",
    'lib/php8.lib' => "{$phpDir}/lib/php8.lib",
    'php8ts.lib' => "{$phpDir}/php8ts.lib",
    'php8.lib' => "{$phpDir}/php8.lib",
];
$phpCoreLibRelativePath = null;
foreach ($phpCoreLibCandidates as $relativePath => $candidate) {
    if (is_file($candidate)) {
        $phpCoreLibRelativePath = $relativePath;
        break;
    }
}
if ($phpCoreLibRelativePath === null) {
    echo "错误: 未找到 php8ts.lib 或 php8.lib\n";
    exit(1);
}

$phpRuntimeDllRelativePath = is_file("{$phpDir}/php8ts.dll")
    ? 'php8ts.dll'
    : (is_file("{$phpDir}/php8.dll") ? 'php8.dll' : null);
if ($phpRuntimeDllRelativePath === null) {
    echo "错误: 未找到 php8ts.dll 或 php8.dll\n";
    exit(1);
}

$phpxIncludeDir = "{$phpxDir}/include";
$phpxLibFile = "{$phpxDir}/lib/phpx.lib";
$windowsLinkLibraryFiles = [
    'gmp.lib',
    'gmpxx.lib',
    'mpfr.lib',
    'libmpdec-4.0.1.dll.lib',
    'libmpdec++-4.0.1.dll.lib',
];
foreach ($windowsLinkLibraryFiles as $libraryFile) {
    $libraryPath = "{$phpDir}/SDK/lib/{$libraryFile}";
    if (!is_file($libraryPath)) {
        echo "错误: Windows 链接依赖库不存在 - {$libraryPath}\n";
        exit(1);
    }
}
$phpxMiscDir = "{$phpxDir}/src/misc";
$phpxDllFile = "{$phpxDir}/build/phpx.dll";
$requiredPhpxPaths = [
    'PHPX include directory' => $phpxIncludeDir,
    'PHPX misc source directory' => $phpxMiscDir,
    'PHPX import library' => $phpxLibFile,
    'PHPX runtime library' => $phpxDllFile,
];
foreach ($requiredPhpxPaths as $description => $path) {
    if (!file_exists($path)) {
        echo "错误: {$description} 不存在 - {$path}\n";
        echo "请先在 {$phpxDir}/build 中执行 nmake phpx\n";
        exit(1);
    }
}

echo "所有文件检查通过\n\n";

$topLevelDir = "tpc_v{$versionId}_{$osType}_{$arch}";
if (is_dir($topLevelDir)) {
    echo "清理旧的临时目录...\n";
    removeDirectory($topLevelDir);
}
mustCreateDirectory($topLevelDir);

$cleanupStage = true;
$cleanupArchive = true;
register_shutdown_function(static function () use (
    &$cleanupStage,
    &$cleanupArchive,
    $topLevelDir,
    $outputFile,
): void {
    if ($cleanupStage && is_dir($topLevelDir)) {
        try {
            removeDirectory($topLevelDir);
        } catch (Throwable $error) {
            fwrite(STDERR, "警告: 无法清理临时目录 {$topLevelDir}: {$error->getMessage()}\n");
        }
    }
    if ($cleanupArchive && is_file($outputFile) && !unlink($outputFile)) {
        fwrite(STDERR, "警告: 无法清理未提交的压缩包 {$outputFile}\n");
    }
});

$packagedCompilerExe = "{$topLevelDir}/{$compilerExe}";
mustCopy($compilerExe, $packagedCompilerExe);
$windowsSetupFile = "{$topLevelDir}/WINDOWS-SETUP.txt";
$windowsSetup = <<<'TEXT'
TypePHP Windows setup
=====================

TypePHP for Windows uses the prebuilt PHPX DLL and import library included in
this package. The Composer path vendor\swoole\phpx is not used on Windows.

Open a command prompt in this directory and run:

    set PHP_HOME=%CD%
    set PHPX_HOME=%CD%\phpx
    set PATH=%CD%;%PATH%

PHPX_HOME must point to the bundled phpx directory. Its required files include:

    phpx\include
    phpx\lib\phpx.lib
    phpx\src\misc

TEXT;
mustWriteFile($windowsSetupFile, $windowsSetup);

// ==================== 4. 保持标准 PE 格式 ====================
// Windows 包依靠 ZIP 压缩。不要使用 UPX 改写 tpc.exe，以避免增加杀毒
// 误报和运行时自解压带来的兼容性变量。
echo "[4/7] 保持标准 PE 可执行文件，不使用 UPX\n\n";

// ==================== 5. 准备 PHP 目录结构 ====================
echo "[5/7] 准备 PHP 目录结构...\n";

echo "创建顶层目录: {$topLevelDir}\n";
echo "复制 {$compilerExe} -> {$topLevelDir}/\n";

// 递归复制 PHP_HOME 下的所有文件到顶层目录
if (!is_dir($phpDir)) {
    echo "警告: PHP 目录不存在 - {$phpDir}\n";
    echo "跳过 PHP 文件复制\n\n";
} else {
    echo "复制 PHP 运行时文件...\n";
    // dev/php8ts.lib 与 SDK/lib/php8ts.lib 重复，可以排除。
    // setup-php 会在 PHP_HOME 留下 PHP 发行包和 PhpManager 等 ZIP 下载缓存；
    // 运行时和 SDK 已经展开，继续打包这些归档只会重复占用空间。
    // php8embed.lib 必须保留；部分 PHP SDK 仅在根目录提供该导入库。
    copyDirectory($phpDir, $topLevelDir, ['dev/php8ts.lib'], null, ['zip']);

    $packagedEmbedLib = "{$topLevelDir}/{$phpEmbedLibRelativePath}";
    if (!is_file($packagedEmbedLib)) {
        echo "错误: php8embed.lib 未复制到打包目录 - {$packagedEmbedLib}\n";
        exit(1);
    }
    echo "  已包含: php8embed.lib\n";
    
    // 统计复制的文件数量
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($topLevelDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    $fileCount = iterator_count($iterator);
    echo "已复制 {$fileCount} 个文件\n";
}

echo "\n";

// ==================== 6. 复制项目文件到 PHP 目录 ====================
echo "[6/7] 复制项目文件到 PHP 目录...\n";

// 在顶层目录下创建子目录
mustCreateDirectory("{$topLevelDir}/examples");

// 复制项目文件
$projectFiles = [
    'README.md' => '.',
    'LICENSE' => '.',
    'composer.json' => '.',
    'examples/hello.php' => 'examples',
];

foreach ($projectFiles as $src => $destDir) {
    $destPath = "{$topLevelDir}/{$destDir}";
    if (!is_dir($destPath)) {
        mustCreateDirectory($destPath);
    }
    mustCopy($src, "{$destPath}/" . basename($src));
    echo "  复制: {$src} -> {$destPath}/\n";
}

// 复制 win32-hello 示例目录（Windows 编程实例）
$win32HelloDir = 'examples/win32-hello';
if (is_dir($win32HelloDir)) {
    echo "  复制: {$win32HelloDir} -> examples/win32-hello/\n";
    // 排除 .obj 文件（MSVC 目标文件）
    copyDirectory($win32HelloDir, "{$topLevelDir}/examples/win32-hello", [], null, ['obj']);
} else {
    echo "  警告: {$win32HelloDir} 目录不存在\n";
}

// 复制 tetris-win32 示例目录（俄罗斯方块游戏）
$tetrisWin32Dir = 'examples/tetris-win32';
if (is_dir($tetrisWin32Dir)) {
    echo "  复制: {$tetrisWin32Dir} -> examples/tetris-win32/\n";
    // 排除 .obj 文件（MSVC 目标文件）
    copyDirectory($tetrisWin32Dir, "{$topLevelDir}/examples/tetris-win32", [], null, ['obj']);
} else {
    echo "  警告: {$tetrisWin32Dir} 目录不存在\n";
}

echo "项目文件复制完成\n\n";

// ==================== 6.3. 复制 vendor 目录 ====================
echo "[6.3/7] 复制 vendor 目录...\n";

$vendorDir = 'vendor';
if (!is_dir($vendorDir)) {
    echo "错误: vendor 目录不存在，请先运行 composer install\n";
    exit(1);
} else {
    echo "复制 vendor 目录...\n";
    // 排除 vendor/swoole/phpx 目录（Windows 下不需要 composer 安装的 phpx）
    copyDirectory($vendorDir, "{$topLevelDir}/vendor", ['swoole/phpx']);
    
    // 统计 vendor 目录的文件数量
    $vendorIterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator("{$topLevelDir}/vendor"),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    $vendorFileCount = iterator_count($vendorIterator);
    echo "已复制 {$vendorFileCount} 个文件\n";
}

echo "\n";

// ==================== 6.5. 复制 phpx 文件 ====================
echo "[6.5/7] 复制 phpx 相关文件...\n";

// 检查 phpx 目录是否存在
if (!is_dir($phpxDir)) {
    echo "错误: phpx 目录不存在 - {$phpxDir}\n";
    exit(1);
} else {
    // 创建 phpx 子目录
    mustCreateDirectory("{$topLevelDir}/phpx/include");
    mustCreateDirectory("{$topLevelDir}/phpx/lib");
    
    // 复制 phpx include 头文件
    copyDirectory($phpxIncludeDir, "{$topLevelDir}/phpx/include");
    echo "  复制: phpx/include -> phpx/include/\n";
    
    // PHPX 目录只保留自身导入库；gmp/mpfr/mpdecimal 位于 PHP SDK/lib。
    mustCopy($phpxLibFile, "{$topLevelDir}/phpx/lib/phpx.lib");
    echo "  复制: phpx.lib -> phpx/lib/\n";
    
    // 复制 phpx/src/misc 目录（Embed/CLI 运行时适配代码）
    mustCreateDirectory("{$topLevelDir}/phpx/src/misc");
    // 排除 .obj 文件（MSVC 目标文件）和 .d 文件（依赖文件）
    copyDirectory($phpxMiscDir, "{$topLevelDir}/phpx/src/misc", [], null, ['obj', 'd']);
    echo "  复制: phpx/src/misc -> phpx/src/misc/\n";
    
    // 复制 phpx.dll 到顶层目录
    mustCopy($phpxDllFile, "{$topLevelDir}/phpx.dll");
    echo "  复制: phpx.dll -> {$topLevelDir}/\n";
    
    echo "phpx 文件复制完成\n\n";
}

// ==================== 7. 创建压缩包 ====================
echo "[7/7] 创建压缩包...\n";

// 删除旧的压缩包
if (file_exists($outputFile)) {
    if (!unlink($outputFile)) {
        throw new RuntimeException("无法删除旧的压缩包: {$outputFile}");
    }
    echo "已删除旧的压缩包\n";
}

// 使用 ZipArchive 创建 zip
if (!class_exists('ZipArchive')) {
    echo "错误: ZipArchive 扩展未启用\n";
    echo "请在 php.ini 中启用 extension=zip\n";
    
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    echo "错误: 无法创建压缩包\n";
    
    exit(1);
}

// 添加文件到压缩包
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($topLevelDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        // 统一使用正斜杠
        $topLevelDirNormalized = str_replace('\\', '/', realpath($topLevelDir));
        $filePathNormalized = str_replace('\\', '/', $filePath);
        $relativePath = substr($filePathNormalized, strlen($topLevelDirNormalized) + 1);
        // 在相对路径前加上顶层目录名
        $zipPath = "{$topLevelDir}/{$relativePath}";
        if (!$zip->addFile($filePath, $zipPath)) {
            throw new RuntimeException("无法将文件加入压缩包: {$filePath}");
        }
    }
}

if (!$zip->close()) {
    throw new RuntimeException("无法完成压缩包写入: {$outputFile}");
}

$requiredArchiveEntries = [
    "{$topLevelDir}/{$compilerExe}",
    "{$topLevelDir}/WINDOWS-SETUP.txt",
    "{$topLevelDir}/phpx.dll",
    "{$topLevelDir}/{$phpRuntimeDllRelativePath}",
    "{$topLevelDir}/{$phpEmbedLibRelativePath}",
    "{$topLevelDir}/{$phpCoreLibRelativePath}",
    "{$topLevelDir}/phpx/include/phpx.h",
    "{$topLevelDir}/phpx/lib/phpx.lib",
    "{$topLevelDir}/phpx/include/typephp_helper.h",
    "{$topLevelDir}/phpx/include/typephp_runtime.h",
];
foreach ($windowsLinkLibraryFiles as $libraryFile) {
    $requiredArchiveEntries[] = "{$topLevelDir}/SDK/lib/{$libraryFile}";
}
$verificationZip = new ZipArchive();
if ($verificationZip->open($outputFile) !== true) {
    throw new RuntimeException("无法重新打开压缩包进行验证: {$outputFile}");
}
foreach ($requiredArchiveEntries as $entry) {
    if ($verificationZip->locateName($entry) === false) {
        $verificationZip->close();
        throw new RuntimeException("压缩包缺少必需文件: {$entry}");
    }
}
$verificationZip->close();
echo "✓ 压缩包创建成功\n\n";

// ==================== 8. 清理和恢复 ====================
echo "[8/8] 清理临时文件...\n";

// 获取文件大小
$packageSize = filesize($outputFile);
$packageSizeMB = formatMegabytes($packageSize);

echo "临时目录: {$topLevelDir}\n";
removeDirectory($topLevelDir);
$cleanupStage = false;
echo "临时目录已清理\n";

$cleanupArchive = false;

echo "\n";

// ==================== 最终报告 ====================
echo "========================================\n";
echo "打包完成！\n";
echo "========================================\n\n";
echo "版本号: {$versionId}\n";
echo "输出文件: {$outputFile}\n";
echo "文件大小: {$packageSizeMB} MB\n\n";
echo "包含内容:\n";
echo "  - {$compilerExe} (编译器可执行文件)\n";
echo "  - WINDOWS-SETUP.txt (Windows 环境变量与 PHPX_HOME 配置说明)\n";
echo "  - phpx.dll (PHPX 运行时库)\n";
echo "  - phpx/include/ (PHPX 头文件)\n";
echo "  - phpx/lib/ (PHPX 库文件)\n";
echo "  - phpx/src/misc/ (PHPX Embed/CLI runtime adapters)\n";
echo "  - PHP 运行时环境 (完整目录结构)\n";
echo "  - vendor/ (Composer 依赖包，无需再次安装)\n";
echo "  - composer.json (Composer 配置文件)\n";
echo "  - README.md/LICENSE (文档)\n";
echo "  - examples/hello.php (PHP 示例代码)\n";
echo "  - examples/win32-hello/ (Windows GUI 编程实例)\n";
echo "  - examples/tetris-win32/ (俄罗斯方块游戏实例)\n\n";
echo "使用说明:\n";
echo "  1. 解压到任意目录\n";
echo "  2. set PHP_HOME=%CD%\n";
echo "  3. set PHPX_HOME=%CD%\\phpx\n";
echo "  4. set PATH=%CD%;%PATH%\n";
echo "  5. 运行: tpc <your_script.php>\n\n";

function resolvePackageVersion(): string
{
    $releaseVersion = getenv('TYPEPHP_PACKAGE_VERSION');
    if (!is_string($releaseVersion) || trim($releaseVersion) === '') {
        $releaseVersion = getenv('TYPEPHP_BUILD_ID');
    }
    if (!is_string($releaseVersion) || trim($releaseVersion) === '') {
        $releaseVersion = getenv('GITHUB_RUN_NUMBER');
    }
    if (!is_string($releaseVersion) || trim($releaseVersion) === '') {
        $releaseVersion = getenv('GITHUB_RUN_ID');
    }
    if (!is_string($releaseVersion) || trim($releaseVersion) === '') {
        throw new RuntimeException(
            'Set TYPEPHP_PACKAGE_VERSION for a release or TYPEPHP_BUILD_ID for a preview build',
        );
    }
    $releaseVersion = trim($releaseVersion);
    if (preg_match('/^v(?=\d)/i', $releaseVersion) === 1) {
        $releaseVersion = substr($releaseVersion, 1);
    }
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $releaseVersion) !== 1) {
        throw new RuntimeException(
            'Package version contains characters that are unsafe in archive names',
        );
    }
    return $releaseVersion;
}

function normalizeArchitecture(string $architecture): string
{
    $architecture = strtolower(trim($architecture));
    $normalized = match ($architecture) {
        'x86_64', 'x86-64', 'amd64', 'x64' => 'x64',
        'aarch64', 'arm64', 'arm64b', 'arm64e' => 'arm64',
        'powerpc64', 'ppc64' => 'ppc64',
        'powerpc64le', 'ppc64le' => 'ppc64le',
        'riscv64' => 'riscv64',
        's390x' => 's390x',
        'loongarch64' => 'loongarch64',
        'mips64' => 'mips64',
        'mips64el' => 'mips64el',
        'sparc64' => 'sparc64',
        default => null,
    };
    if ($normalized !== null) {
        return $normalized;
    }

    // Preserve future 64-bit architecture names while keeping archive names safe.
    if (str_contains($architecture, '64')
        && preg_match('/^[a-z0-9][a-z0-9._-]*$/', $architecture) === 1) {
        return str_replace('-', '_', $architecture);
    }

    throw new RuntimeException("Unsupported or non-64-bit architecture: {$architecture}");
}

function packageUnixLike(): void
{
    $osType = match (PHP_OS_FAMILY) {
        'Linux' => 'linux',
        'Darwin' => 'macos',
        default => throw new RuntimeException('Unsupported operating system: ' . PHP_OS_FAMILY),
    };
    if (PHP_INT_SIZE !== 8) {
        throw new RuntimeException('TypePHP only supports 64-bit systems');
    }

    $arch = normalizeArchitecture(php_uname('m'));

    $binary = 'tpc';
    $requiredFiles = [
        $binary,
        'vendor/autoload.php',
        'vendor/composer/installed.php',
    ];
    foreach ($requiredFiles as $file) {
        if (!is_file($file)) {
            throw new RuntimeException("Required package file not found: {$file}");
        }
    }
    $versionId = resolvePackageVersion();
    $topLevelDir = "tpc_v{$versionId}_{$osType}_{$arch}";
    $outputFile = $topLevelDir . '.tar.gz';

    echo "========================================\n";
    echo "TypePHP {$osType} package\n";
    echo "========================================\n";
    echo "Version: {$versionId}\n";
    echo "Architecture: {$arch}\n";
    echo "Output: {$outputFile}\n\n";

    if (is_dir($topLevelDir)) {
        removeDirectory($topLevelDir);
    }
    mustCreateDirectory($topLevelDir);

    $cleanupStage = true;
    $cleanupArchive = true;
    register_shutdown_function(static function () use (
        &$cleanupStage,
        &$cleanupArchive,
        $topLevelDir,
        $outputFile,
    ): void {
        if ($cleanupStage && is_dir($topLevelDir)) {
            try {
                removeDirectory($topLevelDir);
            } catch (Throwable $error) {
                fwrite(STDERR, "Warning: unable to clean {$topLevelDir}: {$error->getMessage()}\n");
            }
        }
        if ($cleanupArchive && is_file($outputFile) && !unlink($outputFile)) {
            fwrite(STDERR, "Warning: unable to clean uncommitted archive {$outputFile}\n");
        }
    });

    $stagedBinary = "{$topLevelDir}/{$binary}";
    mustCopy($binary, $stagedBinary);
    if (!chmod($stagedBinary, 0755)) {
        throw new RuntimeException("Unable to mark executable: {$stagedBinary}");
    }

    exec('command -v strip 2>/dev/null', $stripPath, $stripStatus);
    if ($stripStatus !== 0) {
        throw new RuntimeException('strip is required for Unix-like packaging');
    }
    $stripFlag = $osType === 'linux' ? '--strip-unneeded' : '-x';
    exec(
        'strip ' . $stripFlag . ' ' . escapeshellarg($stagedBinary) . ' 2>&1',
        $stripOutput,
        $stripStatus,
    );
    if ($stripStatus !== 0) {
        throw new RuntimeException("strip failed:\n" . implode("\n", $stripOutput));
    }

    // Linux and macOS releases intentionally use the target system's libphp,
    // libphpx, and other native dependencies. Composer sources and headers are
    // portable, but test-built host libraries must not enter the archive.
    mustCreateDirectory("{$topLevelDir}/vendor");
    copyDirectory(
        'vendor',
        "{$topLevelDir}/vendor",
        [],
        null,
        ['a', 'dll', 'dylib', 'exe', 'exp', 'lib', 'o', 'obj', 'pdb', 'so'],
    );

    $requiredEntries = [
        "{$topLevelDir}/{$binary}",
        "{$topLevelDir}/vendor/autoload.php",
        "{$topLevelDir}/vendor/composer/installed.php",
    ];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            "{$topLevelDir}/vendor",
            FilesystemIterator::SKIP_DOTS,
        ),
        RecursiveIteratorIterator::LEAVES_ONLY,
    );
    foreach ($files as $file) {
        if ($file->isFile() && isNativeLibraryPath($file->getPathname())) {
            throw new RuntimeException(
                "{$osType} package unexpectedly contains a native library: {$file->getPathname()}",
            );
        }
    }

    exec('command -v tar 2>/dev/null', $tarPath, $tarStatus);
    if ($tarStatus !== 0) {
        throw new RuntimeException('tar is required for Unix-like packaging');
    }
    exec(
        'tar -czf ' . escapeshellarg($outputFile) . ' ' . escapeshellarg($topLevelDir) . ' 2>&1',
        $tarOutput,
        $tarStatus,
    );
    if ($tarStatus !== 0) {
        throw new RuntimeException("tar failed:\n" . implode("\n", $tarOutput));
    }
    exec('tar -tzf ' . escapeshellarg($outputFile) . ' 2>&1', $archiveEntries, $tarStatus);
    if ($tarStatus !== 0) {
        throw new RuntimeException("Unable to verify archive:\n" . implode("\n", $archiveEntries));
    }
    foreach ($requiredEntries as $entry) {
        if (!in_array($entry, $archiveEntries, true)) {
            throw new RuntimeException("Archive is missing required entry: {$entry}");
        }
    }

    removeDirectory($topLevelDir);
    $cleanupStage = false;
    $cleanupArchive = false;

    $sizeMb = formatMegabytes(filesize($outputFile));
    echo "Package successful: {$outputFile} ({$sizeMb} MB)\n";
}

function formatMegabytes(int $bytes): string
{
    return number_format($bytes / 1024 / 1024, 3, '.', '');
}

function isNativeLibraryPath(string $path): bool
{
    return preg_match(
        '/\.(?:a|dll|dylib(?:\.\d+)*|exe|exp|lib|o|obj|pdb|so(?:\.\d+)*)$/i',
        $path,
    ) === 1;
}

/**
 * 递归复制目录
 * @param string $src 源目录
 * @param string $dest 目标目录
 * @param array $excludeDirs 要排除的目录列表（相对于 src 的路径）
 * @param string $baseSrc 原始源目录（用于计算相对路径）
 * @param array $excludeExtensions 要排除的文件扩展名列表（不含点）
 */
function copyDirectory(string $src, string $dest, array $excludeDirs = [], ?string $baseSrc = null, array $excludeExtensions = []): void
{
    if (!is_dir($src)) {
        throw new RuntimeException("源目录不存在: {$src}");
    }
    
    if ($baseSrc === null) {
        $baseSrc = $src;
    }
    
    if (!is_dir($dest)) {
        mustCreateDirectory($dest);
    }
    
    $scannedFiles = scandir($src);
    if ($scannedFiles === false) {
        throw new RuntimeException("无法读取目录: {$src}");
    }
    $files = array_diff($scannedFiles, ['.', '..']);
    foreach ($files as $file) {
        $srcPath = "{$src}/{$file}";
        $destPath = "{$dest}/{$file}";
        
        // 如果是文件，检查扩展名是否在排除列表中
        if (is_file($srcPath) && !empty($excludeExtensions)) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $versionedSharedLibrary = (
                in_array('so', $excludeExtensions, true)
                && preg_match('/\.so(?:\.\d+)*$/i', $file) === 1
            ) || (
                in_array('dylib', $excludeExtensions, true)
                && preg_match('/\.dylib(?:\.\d+)*$/i', $file) === 1
            );
            if ($versionedSharedLibrary || in_array($ext, $excludeExtensions, true)) {
                continue;
            }
        }
        
        // 计算相对于 baseSrc 的路径
        $relativePath = str_replace('\\', '/', substr($srcPath, strlen($baseSrc) + 1));
        
        // 检查当前路径是否在排除列表中
        $shouldExclude = false;
        foreach ($excludeDirs as $excludeDir) {
            $excludeDir = rtrim(str_replace('\\', '/', $excludeDir), '/');
            if ($relativePath === $excludeDir || str_starts_with($relativePath, $excludeDir . '/')) {
                $shouldExclude = true;
                break;
            }
        }
        
        if ($shouldExclude) {
            continue;
        }
        
        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $destPath, $excludeDirs, $baseSrc, $excludeExtensions);
        } else {
            mustCopy($srcPath, $destPath);
        }
    }
}

function mustCreateDirectory(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("无法创建目录: {$dir}");
    }
}

function mustCopy(string $src, string $dest): void
{
    if (!is_file($src)) {
        throw new RuntimeException("源文件不存在: {$src}");
    }
    if (!copy($src, $dest)) {
        throw new RuntimeException("无法复制文件: {$src} -> {$dest}");
    }
}

function mustWriteFile(string $path, string $contents): void
{
    $handle = fopen($path, 'c+b');
    if ($handle === false) {
        throw new RuntimeException("无法打开文件进行写入: {$path}");
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException("无法锁定文件: {$path}");
        }
        if (!ftruncate($handle, 0) || rewind($handle) === false) {
            throw new RuntimeException("无法清空文件: {$path}");
        }
        $length = strlen($contents);
        if (fwrite($handle, $contents) !== $length || !fflush($handle)) {
            throw new RuntimeException("无法写入文件: {$path}");
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

/**
 * 递归删除目录
 */
function removeDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    
    // 使用 RecursiveIteratorIterator 更高效地删除深层目录
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            if (!rmdir($file->getRealPath())) {
                throw new RuntimeException("无法删除目录: {$file->getRealPath()}");
            }
        } else {
            // 移除只读属性（Windows）
            if (PHP_OS_FAMILY === 'Windows' && !is_writable($file->getRealPath())) {
                if (!chmod($file->getRealPath(), 0666)) {
                    throw new RuntimeException("无法修改文件属性: {$file->getRealPath()}");
                }
            }
            if (!unlink($file->getRealPath())) {
                throw new RuntimeException("无法删除文件: {$file->getRealPath()}");
            }
        }
    }
    
    // 最后删除根目录
    if (!rmdir($dir)) {
        throw new RuntimeException("无法删除目录: {$dir}");
    }
}
