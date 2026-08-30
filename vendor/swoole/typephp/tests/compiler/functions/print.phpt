--TEST--
any
--FILE--
<?php
declare(strict_types=1);

interface I { function o(): string; }

readonly class G implements I {
    function __construct(private float $p) {}
    function o(): string {
        return sprintf("$%.2f", $this->p);
    }
}

readonly class B implements I {
    function __construct(private array $i) {}
    function o(): string {
        return sprintf("%d its", count($this->i));
    }
}

function main(): void {
    $x = [new G(1999.99), new B(["A","B","C"])];
    array_map(fn(I $i) => print($i->o() . "\n"), $x);
}
?>
--EXPECT--
$1999.99
3 its
