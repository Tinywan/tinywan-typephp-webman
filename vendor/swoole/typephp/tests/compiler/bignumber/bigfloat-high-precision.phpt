--TEST--
BigFloat arithmetic retains precision beyond IEEE double
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $large = std::bigFloat("1000000000000000000000000000000");
    $one = std::bigFloat("1");
    echo (($large + $one) - $large)->toString(), "\n";

    try {
        $unused = std::bigFloat("not-a-number");
    } catch (ValueError $e) {
        echo "invalid bigfloat caught\n";
    }
}
?>
--EXPECT--
1
invalid bigfloat caught
