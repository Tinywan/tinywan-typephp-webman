--TEST--
Folded is_int/is_float/is_bool must keep evaluating side-effect arguments
--FILE--
<?php
function intSource(): int
{
    echo "int-called\n";
    return 42;
}

function floatSource(): float
{
    echo "float-called\n";
    return 1.5;
}

function boolSource(): bool
{
    echo "bool-called\n";
    return true;
}

function assignmentSource(): int
{
    echo "assignment-called\n";
    return 73;
}

function exceptionSource(): int
{
    echo "exception-called\n";
    throw new RuntimeException('folded-exception');
}

function main(): void
{
    if (is_int(intSource())) {
        echo "is-int\n";
    }
    echo is_float(floatSource()) ? "is-float\n" : "not-float\n";
    $r = is_bool(boolSource());
    echo $r ? "is-bool\n" : "not-bool\n";

    // Plain variables still fold without extra evaluation.
    $n = 7;
    echo is_int($n) ? "var-int\n" : "var-not-int\n";

    // The folded operand must run exactly once and preserve its result.
    $assigned = 0;
    echo is_int($assigned = assignmentSource()) ? "assigned-int\n" : "assigned-not-int\n";
    echo "assigned-value=", $assigned, "\n";

    // Folding the predicate must not suppress an exception from its operand.
    try {
        if (is_int(exceptionSource())) {
            echo "exception-not-thrown\n";
        }
    } catch (RuntimeException $e) {
        echo "caught=", $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int-called
is-int
float-called
is-float
bool-called
is-bool
var-int
assignment-called
assigned-int
assigned-value=73
exception-called
caught=folded-exception
