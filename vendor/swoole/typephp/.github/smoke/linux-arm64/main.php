<?php

function requireLinuxArm64Condition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function main(): void
{
    requireLinuxArm64Condition(PHP_OS_FAMILY === 'Linux', 'Expected PHP_OS_FAMILY=Linux');
    requireLinuxArm64Condition(DIRECTORY_SEPARATOR === '/', 'Expected the Unix directory separator');
    requireLinuxArm64Condition(PHP_ZTS !== 0 && PHP_ZTS !== false, 'Expected a ZTS PHP runtime');
    requireLinuxArm64Condition(linux_native_php_is_zts(), 'The native ZTS macro is not enabled');
    requireLinuxArm64Condition(linux_native_is_arm64(), 'The native compiler target is not ARM64');
    requireLinuxArm64Condition(linux_current_process_id() > 0, 'getpid() failed');
    requireLinuxArm64Condition(linux_online_processor_count() > 0, 'sysconf() returned no processors');
    requireLinuxArm64Condition(linux_uname_machine_is_arm64(), 'uname() did not report ARM64');

    echo 'linux-arm64-smoke-ok:zts';
}
