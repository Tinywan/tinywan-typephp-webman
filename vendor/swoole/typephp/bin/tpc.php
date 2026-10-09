#!/usr/bin/env php
<?php
require __DIR__ . '/bootstrap.php';
require TYPEPHP_ROOT_PATH . '/src/polyfills.php';
require TYPEPHP_ROOT_PATH . '/src/gen_stub.php';
require TYPEPHP_ROOT_PATH . '/src/compiler.php';

runCompiler(
    $argc,
    $argv,
    \TypePhp\Build\CompilerRuntime::source(TYPEPHP_ROOT_PATH, $argv[0]),
);
