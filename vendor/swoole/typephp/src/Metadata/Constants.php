<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp\Metadata;

use TypePhp\CompilerBase;

class Constants
{
    public const string EXTENSION_PREFIX = 'typephp_';

    /** Keep generated project namespaces disjoint from global typephp_* runtime helpers. */
    public const string CPP_PROJECT_NAMESPACE_PREFIX = 'typephp_project_';

    public const array CPP_RESERVED_NAMES = [
        'alignas',
        'alignof',
        'and',
        'and_eq',
        'asm',
        'auto',
        'bitand',
        'bitor',
        'bool',
        'break',
        'case',
        'catch',
        'char',
        'char8_t',
        'char16_t',
        'char32_t',
        'class',
        'compl',
        'const',
        'consteval',
        'constexpr',
        'constinit',
        'const_cast',
        'continue',
        'decltype',
        'default',
        'delete',
        'do',
        'double',
        'dynamic_cast',
        'else',
        'elseif',
        'enum',
        'explicit',
        'export',
        'extends',
        'extern',
        'false',
        'final',
        'finally',
        'float',
        'for',
        'friend',
        'function',
        'global',
        'goto',
        'if',
        'inline',
        'int',
        'long',
        'mutable',
        'namespace',
        'new',
        'noexcept',
        'not',
        'not_eq',
        'nullptr',
        'null',
        'or',
        'or_eq',
        'operator',
        'private',
        'protected',
        'public',
        'register',
        'reinterpret_cast',
        'requires',
        'return',
        'short',
        'signed',
        'sizeof',
        'static',
        'static_assert',
        'static_cast',
        'struct',
        'switch',
        'template',
        'this',
        'thread_local',
        'throw',
        'true',
        'try',
        'typedef',
        'typeid',
        'typename',
        'union',
        'unsigned',
        'using',
        'var',
        'virtual',
        'void',
        'volatile',
        'wchar_t',
        'while',
        'xor',
        'xor_eq',
        'stdin',  // C stdio macros
        'stdout',
        'stderr',
        'pipe',
        'errno', // Linux error code
        'this_', // phpx keywords
        'fake_scope_guard',
    ];

    public const array UNSUPPORTED_FUNCTIONS = [
        'extract',
        'get_defined_vars',
    ];

    public const array COMPILER_OPTIONS = [
        'optimize' => [
            'prefix' => 'O',
            'longPrefix' => 'optimize',
            'description' => 'Set the optimization level of the gcc compiler to 0 by default',
            'required' => false,
            'castTo' => 'int',
            'defaultValue' => 0,
        ],
        'output' => [
            'prefix' => 'o',
            'longPrefix' => 'output',
            'description' => 'Output file',
        ],
        'help' => [
            'prefix' => 'h',
            'longPrefix' => 'help',
            'description' => 'Show help',
            'noValue' => true,
        ],
        'version' => [
            'prefix' => 'v',
            'longPrefix' => 'version',
            'description' => 'Show Version',
            'noValue' => true,
        ],
        'profile' => [
            'longPrefix' => 'profile',
            'description' => 'Enable performance profiling',
            'required' => false,
            'noValue' => true,
        ],
        'no-literal-strings' => [
            'longPrefix' => 'no-literal-strings',
            'description' => 'Disable literal strings optimization',
            'required' => false,
            'noValue' => true,
        ],
        'php-version' => [
            'longPrefix' => 'php-version',
            'description' => 'PHP language version to accept (8.4 or 8.5; default: 8.5)',
            'required' => false,
        ],
        'force' => [
            'prefix' => 'f',
            'longPrefix' => 'force',
            'description' => 'Force recompile phpx misc files (ignore cache)',
            'required' => false,
            'noValue' => true,
        ],
        'mode' => [
            'longPrefix' => 'mode',
            'prefix' => 'm',
            'description' => 'Build mode, -m bin(binary), -m lib(shared library), or -m ext(PHP extension), default: bin',
            'required' => false,
            'defaultValue' => CompilerBase::BUILD_MODE_BIN,
        ],
        'run' => [
            'prefix' => 'r',
            'longPrefix' => 'run',
            'description' => 'Run the compiled binary after build',
            'noValue' => true,
        ],
        // Internal development option used to locate translation issues on a specific line. Do not write it into user documentation.
        'debug-line' => [
            'longPrefix' => 'debug-line',
            'description' => 'Enable debug line',
            'required' => false,
            'defaultValue' => 0,
        ],
        'debug' => [
            'longPrefix' => 'debug',
            'description' => 'Enable debug mode (auto-disable optimizations, add debug symbols)',
            'required' => false,
            'noValue' => true,
        ],
        'job' => [
            'prefix' => 'j',
            'longPrefix' => 'job',
            'description' => 'Number of jobs to run in parallel',
            'required' => false,
            'defaultValue' => 4,
        ],
        'no-console' => [
            'longPrefix' => 'no-console',
            'description' => 'Hide console window (Windows only, use /SUBSYSTEM:WINDOWS)',
            'required' => false,
            'noValue' => true,
        ],
        'sanitize' => [
            'longPrefix' => 'sanitize',
            'description' => 'Enable sanitizers (address, undefined, etc.) for debugging',
            'required' => false,
            'defaultValue' => '',
        ],
        'cxx-std' => [
            'longPrefix'  => 'cxx-std',
            'description' => 'C++ standard version (c++17, c++20, etc.)',
            'required'    => false,
            'defaultValue' => 'c++17',
        ],
        'march' => [
            'longPrefix'  => 'march',
            'description' => 'Target CPU instruction set for code generation (e.g. native, x86-64-v3, armv8-a)',
            'required'    => false,
            'defaultValue' => '',
        ],
        'compiler' => [
            'longPrefix'  => 'compiler',
            'description' => 'C++ compiler command to use (e.g. --compiler=/usr/bin/clang)',
            'required'    => false,
            'defaultValue' => '',
        ],
        'target-platform' => [
            'longPrefix'  => 'target-platform',
            'description' => 'Cross-compilation target triple (e.g. aarch64-linux-gnu, x86_64-w64-mingw32)',
            'required'    => false,
            'defaultValue' => '',
        ],
        'no-color' => [
            'longPrefix'  => 'no-color',
            'description' => 'Disable ANSI color output',
            'required'    => false,
            'noValue'     => true,
        ],
        'build-dir' => [
            'longPrefix'  => 'build-dir',
            'description' => 'Specify the build directory for generated C++ code',
            'required'    => false,
            'defaultValue' => '',
        ],
        'dry' => [
            'longPrefix'  => 'dry',
            'description' => 'Dry run: only generate C++ code, do not compile or link',
            'required'    => false,
            'noValue'     => true,
        ],
        'include-path' => [
            'prefix'      => 'I',
            'longPrefix'  => 'include-path',
            'description' => 'Add an additional C++ include directory (repeatable)',
            'required'    => false,
            'multiple'    => true,
        ],
        'define' => [
            'prefix'      => 'D',
            'longPrefix'  => 'define',
            'description' => 'Define a preprocessor macro (repeatable, e.g. -D FOO=bar)',
            'required'    => false,
            'multiple'    => true,
        ],
        'no-progress' => [
            'longPrefix'  => 'no-progress',
            'description' => 'Disable progress bar, output per-file compilation progress line by line',
            'required'    => false,
            'noValue'     => true,
        ],
        'lto' => [
            'longPrefix'  => 'lto',
            'description' => 'Enable Link Time Optimization (-flto)',
            'required'    => false,
            'noValue'     => true,
        ],
        'format' => [
            'longPrefix'  => 'format',
            'description' => 'Enable clang-format code formatting (disabled by default)',
            'required'    => false,
            'noValue'     => true,
        ],
        'link-lib' => [
            'prefix'      => 'l',
            'longPrefix'  => 'link-lib',
            'description' => 'Link against a library (repeatable, e.g. -lcurl)',
            'required'    => false,
            'multiple'    => true,
        ],
        'link-path' => [
            'prefix'      => 'L',
            'longPrefix'  => 'link-path',
            'description' => 'Add a library search path (repeatable, e.g. -L/usr/local/lib)',
            'required'    => false,
            'multiple'    => true,
        ],
        'full-static' => [
            'longPrefix'  => 'full-static',
            'description' => 'Enable fully-static linking using the bundled SDK (phpx/full-static/sdk)',
            'required'    => false,
            'noValue'     => true,
        ],
    ];

    /**
     * MSVC compiler warning suppression list.
     * These warnings come from Windows SDK and PHP SDK headers and are compiler noise that does not affect functionality.
     *
     * @var array<string, string> key is the warning number, value is the description
     */
    public const array MSVC_SUPPRESSED_WARNINGS = [
        '4244' => '类型转换可能丢失数据 (int -> smaller type)',
        '4242' => '类型转换可能丢失数据 (similar to C4244)',
        '4146' => '一元负运算符应用于无符号类型',
        '4820' => '结构体成员后有填充字节（内存对齐）',
        '4464' => '相对包含路径含 ".."',
        '4365' => '有符号/无符号转换',
        '4127' => '条件表达式是常量（如 while(1)）',
        '4668' => '未定义的宏当 0 处理（#ifdef __GNUC__）',
        '4626' => '赋值运算符被隐式删除（const 成员）',
        '5027' => '移动赋值运算符被隐式删除',
        '5219' => '隐式转换警告',
        '5220' => 'volatile 成员警告',
        '4100' => '未使用的参数',
        '5039' => '使用未定义的函数',
        '4101' => '未使用的局部变量',
        '4102' => '未引用的标签',
        '4800' => '从整数到布尔类型的隐式转换警告',
        '5045' => 'Spectre 缓解警告',
        '5264' => '未使用 const 变量',
        '5246' => '子对象的初始化应当包装在大括号内',
        '4388' => '有符号/无符号不匹配',
        '4623' => '已将默认构造函数隐式定义为“已删除”',
        '4611' => '_setjmp 和 C++ 对象析构之间的交互是不可移植的',
        '4574' => '使用了 #if 预处理器指令去检查一个被定义为 0 或 1 的宏',
    ];
}
