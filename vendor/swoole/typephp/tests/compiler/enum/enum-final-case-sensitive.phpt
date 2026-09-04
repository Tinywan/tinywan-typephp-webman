--TEST--
Enums are final and case names remain case-sensitive
--FILE--
<?php

enum Suit
{
    case Hearts;
    case hearts;
}

function main(): void
{
    $reflection = new ReflectionClass(Suit::class);
    var_dump($reflection->isFinal());
    foreach (Suit::cases() as $case) {
        var_dump($case->name);
    }
}
?>
--EXPECT--
bool(true)
string(6) "Hearts"
string(6) "hearts"
