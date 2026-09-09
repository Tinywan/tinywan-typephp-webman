<?php

/**
 * Smoke test for --full-static builds.
 *
 * The fully-static artifact embeds its own PHP runtime from the bundled SDK, so
 * this exercises the parts of that runtime most likely to break: the module
 * globals set up during php_module_startup (pcre was where a musl/glibc thread-
 * local storage mismatch used to crash), plus the usual string/array/JSON paths.
 *
 * The output is a fixed token so both the x64 and arm64 workflows can compare
 * it exactly, regardless of the matrix PHP version installed on the runner.
 */

function requireFullStatic(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function main(): void
{
    // pcre reads its compile context from a ZTS module global allocated during
    // php_module_startup; it is the first thing to break when the TLS layout is
    // inconsistent with the C runtime that initialised the thread pointer.
    requireFullStatic(preg_match('/^(\d+)\.(\d+)/', PHP_VERSION, $m) === 1, 'PHP_VERSION did not match');

    $words = ['typephp', 'aot', 'static'];
    sort($words);
    requireFullStatic(implode(',', $words) === 'aot,static,typephp', 'sort() failed');

    requireFullStatic(strtoupper('musl') === 'MUSL', 'strtoupper() failed');
    requireFullStatic(json_encode(['ok' => true]) === '{"ok":true}', 'json_encode() failed');

    $sum = 0;
    for ($i = 0; $i < 100000; $i++) {
        $sum += $i;
    }
    requireFullStatic($sum === 4999950000, 'loop result mismatch');

    echo "full-static-smoke-ok\n";
}
