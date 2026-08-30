#!/usr/bin/env php
<?php
require __DIR__ . '/src/polyfills.php';
include $argv[1];
main($argc, $argv);