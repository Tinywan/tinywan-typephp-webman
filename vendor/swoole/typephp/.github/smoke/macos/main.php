<?php

function requireMacosCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function main(): void
{
    requireMacosCondition(PHP_OS_FAMILY === 'Darwin', 'Expected PHP_OS_FAMILY=Darwin');
    requireMacosCondition(DIRECTORY_SEPARATOR === '/', 'Expected the Unix directory separator');
    requireMacosCondition(PHP_ZTS !== 0 && PHP_ZTS !== false, 'Expected a ZTS PHP runtime');
    requireMacosCondition(macos_native_php_is_zts(), 'The native ZTS macro is not enabled');
    requireMacosCondition(macos_native_is_arm64(), 'The native compiler target is not ARM64');
    requireMacosCondition(macos_current_process_id() > 0, 'getpid() failed');
    requireMacosCondition(macos_logical_processor_count() > 0, 'sysctl() returned no processors');
    requireMacosCondition(macos_has_mach_host_port(), 'mach_host_self() failed');

    echo 'macos-arm64-smoke-ok:zts';
}
