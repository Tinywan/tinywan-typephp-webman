--TEST--
Optimized numeric builtins preserve strict union parameter validation
--FILE--
<?php
declare(strict_types=1);

function main(): void
{
    var_dump(abs(-3));
    var_dump(sqrt(9));
    var_dump(floor(1.75));
    var_dump(ceil(1.25));
    var_dump(round(1.25, 1));

    try {
        abs('3');
        echo "abs=missing TypeError\n";
    } catch (TypeError $error) {
        echo "abs=TypeError\n";
    }
    try {
        sqrt('9');
        echo "sqrt=missing TypeError\n";
    } catch (TypeError $error) {
        echo "sqrt=TypeError\n";
    }
    try {
        floor('1.75');
        echo "floor=missing TypeError\n";
    } catch (TypeError $error) {
        echo "floor=TypeError\n";
    }
    try {
        ceil('1.25');
        echo "ceil=missing TypeError\n";
    } catch (TypeError $error) {
        echo "ceil=TypeError\n";
    }
    try {
        round('1.25');
        echo "round=missing TypeError\n";
    } catch (TypeError $error) {
        echo "round=TypeError\n";
    }
}
?>
--EXPECT--
int(3)
float(3)
float(1)
float(2)
float(1.3)
abs=TypeError
sqrt=TypeError
floor=TypeError
ceil=TypeError
round=TypeError
