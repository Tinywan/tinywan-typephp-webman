<?php

$bytes = random_bytes(2 * 1024 * 1024);

$out = 'static const unsigned char data[] = {';

for ($i = 0; $i < strlen($bytes); $i++) {
    $out .= ord($bytes[$i]) . ', ';
    if ($i % 32 == 0) {
        $out .= "\n\t";
    }
}

$out .= '};' . PHP_EOL;

file_put_contents(__DIR__ . '/../tmp/const_c_array.c', $out);