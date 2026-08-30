--TEST--
file extension classifier with pathinfo and in_array
--FILE--
<?php

function file_name_of(string $path): string
{
    return pathinfo($path, PATHINFO_FILENAME);
}

function file_ext_of(string $path): string
{
    return pathinfo($path, PATHINFO_EXTENSION);
}

function classify_source_file(string $file): string
{
    $ext = file_ext_of($file);
    if (in_array($ext, ['php', 'inc', 'phpt'])) {
        return 'php:' . file_name_of($file);
    }
    if (in_array($ext, ['cc', 'cpp', 'cxx'])) {
        return 'cpp:' . file_name_of($file);
    }
    if (in_array($ext, ['c', 'h', 'hpp'])) {
        return 'native:' . file_name_of($file);
    }
    return 'other:' . $ext;
}

function main(): void
{
    var_dump(classify_source_file('/tmp/src/main.php'));
    var_dump(classify_source_file('lib/compiler.cxx'));
    var_dump(classify_source_file('include/aot.hpp'));
    var_dump(classify_source_file('README'));
}
?>
--EXPECT--
string(8) "php:main"
string(12) "cpp:compiler"
string(10) "native:aot"
string(6) "other:"
