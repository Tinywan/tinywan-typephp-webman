<?php
$dir = '/home/htf/workspace/c/php-8.0.12/ext/standard/';

$outDir = __DIR__ . '/../config/';

$funcs = [];
$files = glob($dir . '*.c');
$index = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    preg_match_all('/PHP_FUNCTION\(([a-z0-9_]+)\)/i', $content, $match);
    if (empty($match[1])) {
        continue;
    }
    foreach ($match[1] as $fn) {
        $funcs[] = $fn;
    }
}

shuffle($funcs);

$_funcs = [];
foreach ($funcs as $fn) {
    $_funcs['fn_' . random_int(100000, 999999) . str_pad($index++, 3, '0') . random_int(100, 999)] = $fn;
}

function dumpVar($file, $var)
{
    file_put_contents($file, "<?php\nreturn " . var_export($var, 1) . ";\n");
}

dumpVar($outDir . 'functions.php', $_funcs);

$constants = get_defined_constants();
$ignore_constants = array_flip(require __DIR__ . '/ignore_constants.php');
foreach ($constants as $k => $v) {
    if (isset($ignore_constants[$k])) {
        unset($constants[$k]);
    }
}

dumpVar($outDir . 'constants.php', $constants);
