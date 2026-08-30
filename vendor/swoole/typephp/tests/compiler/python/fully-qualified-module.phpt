--TEST--
Fully qualified Python modules do not require use declarations
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php
namespace App {
    function usePythonModuleWithoutAlias(): void
    {
        \python\math\sqrt(16);
        \Python\math\pi;
    }
}

namespace {
    function main(): void
    {
        App\usePythonModuleWithoutAlias();
        echo "qualified module access works\n";
    }
}
?>
--EXPECT--
qualified module access works
