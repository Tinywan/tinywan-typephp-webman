--TEST--
PHP function and constant imports resolve Python symbols
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php
namespace App {
    use function python\len;
    use function python\abs as py_abs;
    use function python\math\sqrt as py_sqrt;
    use const python\math\pi as py_pi;

    function importedPythonSymbols(): void
    {
        len([1, 2, 3]);
        py_abs(-4);
        py_sqrt(16);
        py_pi;
        echo "imported Python symbols work\n";
    }
}

namespace {
    function main(): void
    {
        App\importedPythonSymbols();
    }
}
?>
--EXPECT--
imported Python symbols work
