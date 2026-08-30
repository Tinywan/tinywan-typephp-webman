--TEST--
unix library paths normalized into linker flags
--FILE--
<?php

function make_unix_link_lib_flags(array $libraries, string $ext): string
{
    $flags = [];
    foreach ($libraries as $lib) {
        $libName = basename($lib);
        if (str_starts_with($libName, 'lib')) {
            $libName = substr($libName, 3);
        }
        $libName = preg_replace('/\.(a|' . $ext . ')$/', '', $libName);

        $flags[] = '-l' . $libName;
    }

    return implode(' ', $flags);
}

function main(): void
{
    var_dump(make_unix_link_lib_flags(['/usr/lib/libssl.so', 'libcrypto.a', 'z'], 'so'));
    var_dump(make_unix_link_lib_flags(['libcustom.dylib', '/opt/lib/libplain.txt'], 'dylib'));
}
?>
--EXPECT--
string(18) "-lssl -lcrypto -lz"
string(20) "-lcustom -lplain.txt"
