#!/usr/bin/env php
<?php
require __DIR__ . '/bootstrap.php';
require TYPEPHP_ROOT_PATH . '/src/polyfills.php';
require TYPEPHP_ROOT_PATH . '/src/gen_stub.php';
require TYPEPHP_ROOT_PATH . '/src/compiler.php';

const TYPEPHP_PHP_SCRIPT_ENTRY = true;
main($argc, $argv);
