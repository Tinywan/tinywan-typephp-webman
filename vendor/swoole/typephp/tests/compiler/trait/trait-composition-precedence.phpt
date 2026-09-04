--TEST--
Trait composition: concrete fulfills abstract, alias keeps static, class method wins
--FILE--
<?php

// Multiple adaptations of the same method each derive from the original
// method: the same-name visibility change must not leak into the alias,
// regardless of the order the adaptations are listed in.
trait AliasSource {
    public static function value(): string { return "value"; }
}
class AliasConsumer {
    use AliasSource { value as protected; value as alias; }
    public static function callValue(): string { return static::value(); }
}
class AliasConsumerReversed {
    use AliasSource { value as alias; value as protected; }
    public static function callValue(): string { return static::value(); }
}

// A concrete trait method fulfills an abstract requirement from another
// trait, regardless of the order the traits are listed in.
trait NeedsName { abstract public function name(): string; }
trait HasName { public function name(): string { return "HasName"; } }
class AbstractFirst { use NeedsName, HasName; }
class ConcreteFirst { use HasName, NeedsName; }

// An alias visibility change keeps the `static` flag.
trait Maker {
    public static function make(): string { return "made"; }
}
class Factory {
    use Maker { make as protected; }
    public static function build(): string { return static::make(); }
}

// An alias under a new name keeps the `static` flag too.
trait Counter {
    public static function count7(): int { return 7; }
}
class Stats {
    use Counter { count7 as protected seven; }
    public static function total(): int { return static::seven(); }
}

// The class's own method wins over two same-name trait methods without
// this counting as a trait-vs-trait conflict.
trait WhoA { public function who(): string { return "WhoA"; } }
trait WhoB { public function who(): string { return "WhoB"; } }
class Self1 { use WhoA, WhoB; public function who(): string { return "Self1"; } }

// A method fulfilling an abstract requirement does not need to be textually
// identical: contravariant parameters and covariant returns are valid.
trait NeedsLabel { abstract public function label(int|string $v): iterable; }
class WiderLabel {
    use NeedsLabel;
    public function label(int|string|float $v): array { return ["label:$v"]; }
}

function main(): void
{
    echo (new AbstractFirst())->name(), "\n";
    echo (new ConcreteFirst())->name(), "\n";
    echo Factory::build(), "\n";
    echo Stats::total(), "\n";
    echo (new Self1())->who(), "\n";
    echo AliasConsumer::alias(), " ", AliasConsumer::callValue(), "\n";
    echo AliasConsumerReversed::alias(), " ", AliasConsumerReversed::callValue(), "\n";
    foreach ((new WiderLabel())->label(1) as $v) { echo $v, "\n"; }
}
?>
--EXPECT--
HasName
HasName
made
7
Self1
value value
value value
label:1
