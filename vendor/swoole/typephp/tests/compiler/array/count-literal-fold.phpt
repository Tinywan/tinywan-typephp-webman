--TEST--
count() on an array literal keeps spreads, duplicate keys and element side effects
--FILE--
<?php
class KnownClass
{
    public const KNOWN = 1;
}

class MagicHolder
{
    public function __get(string $name): int
    {
        echo "get-{$name}\n";
        return 1;
    }
}

function bump(): int
{
    echo "bump\n";
    return 1;
}

function main()
{
    // Element expressions must still run.
    var_dump(count([bump(), bump()]));

    // A repeated key collapses onto the first one.
    var_dump(count(['a' => 1, 'a' => 2]));

    // A spread contributes a count only known at runtime.
    $rest = [1, 2, 3, 4, 5];
    var_dump(count([...$rest, 9]));

    // Side effects of the elements must be observable afterwards.
    $i = 0;
    var_dump(count([$i++, $i++]));
    var_dump($i);

    // An undefined constant must still raise the same Error PHP raises.
    try {
        var_dump(count([UNDEFINED_COUNT_LITERAL]));
        echo "constant-error-not-thrown\n";
    } catch (Error $e) {
        echo "caught=", $e->getMessage(), "\n";
    }

    // A missing class constant on a known class must also still throw.
    try {
        var_dump(count([KnownClass::MISSING]));
        echo "class-constant-error-not-thrown\n";
    } catch (Error $e) {
        echo "caught=", $e->getMessage(), "\n";
    }

    // An interpolated string may invoke __get(), which must still happen.
    $object = new MagicHolder();
    var_dump(count(["{$object->property}"]));

    // A by-reference item binds the source variable instead of reading it.
    $ref = 1;
    var_dump(count([&$ref]));

    // A defined class constant is still evaluated, not discarded.
    var_dump(count([KnownClass::KNOWN]));

    // Plain literals stay eligible for the compile-time fold.
    var_dump(count([1, 2, 3]));
    var_dump(count([[1, 2], [3]]));
    var_dump(count([1.5, 'text', true, false, null]));
    var_dump(count([-2, +3, -1.5]));
    var_dump(count([]));

    // Argument unpacking must happen before count() receives its arguments.
    $countArgs = [[1, 2, 3]];
    var_dump(count(...$countArgs));
    var_dump(count(...[[1, 2, 3]]));

    // An empty unpack must retain Zend's argument-count validation.
    try {
        var_dump(count(...[]));
        echo "argument-count-error-not-thrown\n";
    } catch (ArgumentCountError $e) {
        echo "caught=", $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
bump
bump
int(2)
int(1)
int(6)
int(2)
int(2)
caught=Undefined constant "UNDEFINED_COUNT_LITERAL"
caught=Undefined constant KnownClass::MISSING
get-property
int(1)
int(1)
int(1)
int(3)
int(2)
int(5)
int(3)
int(0)
int(3)
int(3)
caught=count() expects at least 1 argument, 0 given
