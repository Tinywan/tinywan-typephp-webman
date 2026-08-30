#!/usr/bin/env php
<?php
if ($argc < 2) {
    echo "Usage: php opcode.php <file>\n";
    exit(1);
}
$file = $argv[1];
if (!file_exists($file)) {
    echo "File not found: " . $file . "\n";
    exit(1);
}
shell_exec("php -d zend_extension=opcache -d opcache.enable_cli=On -d opcache.opt_debug_level=0x10000 " . $file);