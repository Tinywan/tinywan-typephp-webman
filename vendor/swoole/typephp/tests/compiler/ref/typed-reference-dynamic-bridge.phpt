--TEST--
Typed native references bridge safely across mixed and dynamic Zend calls
--FILE--
<?php

function replaceMixed(mixed &$value, mixed $replacement): void
{
    $value = $replacement;
}

function observePair(mixed $first, mixed $second): void
{
    var_dump($first, $second);
}

function runtimeValue(bool $invalid): mixed
{
    return $invalid ? 'invalid' : 4;
}

function incrementTyped(int &$value): int
{
    return ++$value;
}

function appendMixed(mixed &$value, int $item): int
{
    $value[] = $item;
    return count($value);
}

function main(): void
{
    $int = 1;
    replaceMixed($int, 2);
    var_dump($int);

    try {
        replaceMixed($int, 'invalid');
    } catch (TypeError $error) {
        echo "type error\n";
    }
    var_dump($int);

    $typedAlias =& $int;
    $typedAlias = runtimeValue(false);
    try {
        $typedAlias = runtimeValue(true);
    } catch (TypeError $error) {
        echo "assignment type error\n";
    }
    var_dump($int, $typedAlias);

    // A Var -> typed-ref bridge nested in another call must commit before the
    // following outer argument observes the same source variable.
    $dynamicInt = std::any(20);
    var_dump(incrementTyped($dynamicInt), $dynamicInt);

    $increment = function (&$value): int {
        $value++;
        return $value;
    };
    // The inner bridge must commit before the second outer argument is read.
    observePair($increment(std::ref($int)), $int);

    $thrower = function (&$value): void {
        $value = 99;
        throw new RuntimeException('stop');
    };
    try {
        $thrower(std::ref($int));
    } catch (RuntimeException $error) {
        echo "caught\n";
    }
    var_dump($int);

    // Both arguments have one canonical root and must share one temporary
    // zend_reference within this call.
    $alias =& $int;
    $mutateBoth = function (&$left, &$right): void {
        $left = 10;
        $right++;
    };
    $mutateBoth(std::ref($int), std::ref($alias));
    var_dump($int, $alias);

    // A dynamic callee may not retain a temporary reference beyond the call.
    $box = new stdClass();
    $escape = function (&$value) use ($box): void {
        $box->value =& $value;
    };
    try {
        $escape(std::ref($int));
    } catch (Error $error) {
        echo "escape error\n";
    }
    var_dump($int, $box->value);

    // Compiler-owned unpack containers must release their temporary
    // reference before the bridge performs its escape check and write-back.
    $add = function (&$value, int $amount): void {
        $value += $amount;
    };
    $tail = [2];
    $add(std::ref($int), ...$tail);
    var_dump($int);

    $string = 'a';
    replaceMixed($string, 'b');
    $float = 1.5;
    replaceMixed($float, 2.5);
    $bool = false;
    replaceMixed($bool, true);
    $array = [1];
    replaceMixed($array, [2]);
    var_dump($string, $float, $bool, $array);

    // RefWrap writeback is part of evaluating the call expression. A later
    // array item must observe it, for both compact and statement-built array
    // literal lowering paths.
    $array = [1];
    $orderedList = [appendMixed($array, 2), $array];
    $orderedMap = ['count' => appendMixed($array, 3), 0 => $array];
    var_dump($orderedList, $orderedMap);
}

?>
--EXPECT--
int(2)
type error
int(2)
assignment type error
int(4)
int(4)
int(21)
int(21)
int(5)
int(5)
caught
int(99)
int(11)
int(11)
escape error
int(11)
int(11)
int(13)
string(1) "b"
float(2.5)
bool(true)
array(1) {
  [0]=>
  int(2)
}
array(2) {
  [0]=>
  int(2)
  [1]=>
  array(2) {
    [0]=>
    int(1)
    [1]=>
    int(2)
  }
}
array(2) {
  ["count"]=>
  int(3)
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
}
