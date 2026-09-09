<?php

function requireWindowsCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function main(int $argc, array $argv): void
{
    requireWindowsCondition($argc === 2, 'Expected the thread-safety mode argument');

    $expectedZts = $argv[1] === 'zts';
    requireWindowsCondition(PHP_OS_FAMILY === 'Windows', 'Expected PHP_OS_FAMILY=Windows');
    requireWindowsCondition(DIRECTORY_SEPARATOR === '\\', 'Expected the Windows directory separator');
    // PHP exposes PHP_ZTS as either an integer-like or boolean-like constant,
    // depending on how the runtime constants were imported. Test its meaning,
    // rather than its representation.
    $runtimeZts = PHP_ZTS !== 0 && PHP_ZTS !== false;
    requireWindowsCondition($runtimeZts === $expectedZts, 'PHP_ZTS does not match the CI matrix');
    requireWindowsCondition(windows_php_is_zts() === $expectedZts, 'The native ZTS macro does not match PHP_ZTS');
    requireWindowsCondition(windows_current_process_id() > 0, 'GetCurrentProcessId() failed');
    requireWindowsCondition(windows_has_module_handle(), 'GetModuleHandleW() failed');
    requireWindowsCondition(windows_logical_processor_count() > 0, 'GetNativeSystemInfo() returned no processors');

    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
        . 'typephp-windows-smoke-' . windows_current_process_id() . '.txt';
    requireWindowsCondition(file_put_contents($path, 'windows-file-api') === 16, 'Windows file write failed');
    requireWindowsCondition(file_get_contents($path) === 'windows-file-api', 'Windows file read failed');
    requireWindowsCondition(unlink($path), 'Windows file cleanup failed');

    echo 'windows-smoke-ok:', $expectedZts ? 'zts' : 'nts';
}
