--TEST--
BigInt arithmetic operations
--FILE--
<?php
declare(strict_types=1);
use native_types;

function main(): void {
    $a = 12345678901234567890;
    $b = 98765432109876543210;
    echo "a+b="; echo $a->add($b)->toString(); echo "\n";
    echo "b-a="; echo $b->sub($a)->toString(); echo "\n";
    echo "a*b="; echo $a->mul($b)->toString(); echo "\n";
    echo "b/a="; echo $b->div($a)->toString(); echo "\n";
    echo "b%a="; echo $b->mod($a)->toString(); echo "\n";
    echo "neg(a)="; echo $a->neg()->toString(); echo "\n";
    echo "abs(neg(a))="; echo $a->neg()->abs()->toString(); echo "\n";
}
?>
--EXPECT--
a+b=111111111011111111100
b-a=86419753208641975320
a*b=1219326311370217952237463801111263526900
b/a=8
b%a=900000000090
neg(a)=-12345678901234567890
abs(neg(a))=12345678901234567890
