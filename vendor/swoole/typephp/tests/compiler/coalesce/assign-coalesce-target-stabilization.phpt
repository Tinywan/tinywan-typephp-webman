--TEST--
??= stabilizes value containers, dynamic property names, and temporary lifetimes
--FILE--
<?php
function makeArray(): array { echo "ARRAY\n"; return []; }
function keyName(): string { echo "KEY\n"; return 'value'; }

class Box {
    public mixed $value = null;
    public static mixed $slot = null;
    public function __destruct() { echo "DESTRUCT\n"; }
}

function makeBox(): object { echo "MAKE\n"; return new Box(); }
function propertyName(): string { echo "NAME\n"; return 'value'; }
function slotName(): string { echo "SLOT\n"; return 'slot'; }
function rhs(): int { echo "RHS\n"; return 42; }

function main(): void
{
    // A value-producing container is evaluated once, before the key.
    var_dump(makeArray()[keyName()] ??= 42);
    // The materialized receiver dies at the end of the statement.
    makeBox()->value ??= 42;
    echo "AFTER\n";
    // A dynamic instance property name is evaluated once, on both branches.
    $box = new Box();
    $box->{propertyName()} ??= rhs();
    var_dump($box->value);
    $box->{propertyName()} ??= rhs();
    var_dump($box->value);
    // A dynamic static property name is evaluated once.
    Box::${slotName()} ??= rhs();
    var_dump(Box::$slot);
}
?>
--EXPECT--
ARRAY
KEY
int(42)
MAKE
DESTRUCT
AFTER
NAME
RHS
int(42)
NAME
int(42)
SLOT
RHS
int(42)
DESTRUCT
