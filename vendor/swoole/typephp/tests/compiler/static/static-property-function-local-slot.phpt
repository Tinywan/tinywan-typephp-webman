--TEST--
Static-property slot caches retain live values, references, inheritance and late static binding
--FILE--
<?php

class StaticSlotBase
{
    public static mixed $shared = 'base';
    public static mixed $separate = 'base';

    public static function mutateShared(): array
    {
        $before = self::$shared;
        self::$shared = null;
        $afterNull = self::$shared;
        $reference = &self::$shared;
        $reference = 'reference';
        return [$before, $afterNull, self::$shared];
    }

    public static function mutateLate(string $value): array
    {
        $before = static::$separate;
        static::$separate = null;
        $afterNull = static::$separate;
        static::$separate = $value;
        return [static::class, $before, $afterNull, static::$separate];
    }
}

class StaticSlotInherited extends StaticSlotBase
{
}

class StaticSlotChildA extends StaticSlotBase
{
    public static mixed $separate = 'a';
}

class StaticSlotChildB extends StaticSlotBase
{
    public static mixed $separate = 'b';
}

function main(): void
{
    var_dump(StaticSlotBase::mutateShared());
    var_dump(StaticSlotInherited::$shared);
    StaticSlotInherited::$shared = 'child-write';
    var_dump(StaticSlotBase::$shared);

    var_dump(StaticSlotChildA::mutateLate('A'));
    var_dump(StaticSlotChildB::mutateLate('B'));
    var_dump(StaticSlotChildA::$separate, StaticSlotChildB::$separate);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(4) "base"
  [1]=>
  NULL
  [2]=>
  string(9) "reference"
}
string(9) "reference"
string(11) "child-write"
array(4) {
  [0]=>
  string(16) "StaticSlotChildA"
  [1]=>
  string(1) "a"
  [2]=>
  NULL
  [3]=>
  string(1) "A"
}
array(4) {
  [0]=>
  string(16) "StaticSlotChildB"
  [1]=>
  string(1) "b"
  [2]=>
  NULL
  [3]=>
  string(1) "B"
}
string(1) "A"
string(1) "B"
