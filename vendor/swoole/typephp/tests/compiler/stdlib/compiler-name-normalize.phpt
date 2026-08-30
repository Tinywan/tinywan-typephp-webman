--TEST--
compiler command name normalization from command string
--FILE--
<?php

function normalize_compiler_name(string $compilerName): string
{
    $firstToken = strtok(trim($compilerName), ' ');
    if ($firstToken === false || $firstToken === '') {
        return '';
    }

    $name = basename(str_replace('\\', '/', $firstToken));
    $name = strtolower($name);

    return preg_replace('/\.exe$/', '', $name);
}

function main(): void
{
    var_dump(normalize_compiler_name('  C:\\LLVM\\bin\\CLANG++.EXE -O2'));
    var_dump(normalize_compiler_name('/usr/bin/g++ -std=c++20'));
    var_dump(normalize_compiler_name(''));
}
?>
--EXPECT--
string(7) "clang++"
string(3) "g++"
string(0) ""
