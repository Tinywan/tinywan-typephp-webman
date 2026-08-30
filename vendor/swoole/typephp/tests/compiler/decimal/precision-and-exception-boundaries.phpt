--TEST--
Decimal keeps 50 digits and translates native arithmetic errors
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $large = std::decimal("1234567890123456789012345678901234567890123456789");
    echo ($large + 1)->toString(), "\n";

    try {
        $unused = std::decimal("1") / 0;
    } catch (DivisionByZeroError $e) {
        echo "division by zero caught\n";
    }

    try {
        $unused = std::decimal("not-a-decimal");
    } catch (ValueError $e) {
        echo "invalid decimal caught\n";
    }
}
?>
--EXPECT--
1234567890123456789012345678901234567890123456790
division by zero caught
invalid decimal caught
