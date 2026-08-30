--TEST--
preprocessor registers namespace constants
--FILE--
<?php
namespace Preprocessor\NsConst {
    const VALUE = 123;
    const LABEL = "ns-const";

    function readLocal(): string {
        return LABEL . ":" . VALUE;
    }
}

namespace {
    use const Preprocessor\NsConst\VALUE;
    use function Preprocessor\NsConst\readLocal;

    function main(): void {
        var_dump(readLocal());
        var_dump(\Preprocessor\NsConst\LABEL);
        var_dump(VALUE);
    }
}
?>
--EXPECT--
string(12) "ns-const:123"
string(8) "ns-const"
int(123)
