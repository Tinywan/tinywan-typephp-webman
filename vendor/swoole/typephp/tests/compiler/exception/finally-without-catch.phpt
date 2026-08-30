--TEST--
try/finally without catch
--FILE--
<?php
function testFinally(int $x): string {
    $result = "";
    try {
        $result .= "try-{$x}";
        if ($x <= 0) {
            return $result . " early-return";
        }
        if ($x >= 10) {
            throw new Exception("too large");
        }
        $result .= " normal";
    } finally {
        $result .= " finally";
    }
    return $result;
}

function main(): void {
    echo testFinally(5) . "\n";
    echo testFinally(0) . "\n";
    try {
        echo testFinally(20) . "\n";
    } catch (Exception $e) {
        echo "Caught: " . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
try-5 normal finally
try-0 early-return
Caught: too large
