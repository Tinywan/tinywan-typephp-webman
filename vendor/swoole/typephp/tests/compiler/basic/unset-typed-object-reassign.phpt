--TEST--
unset typed object retains its declared class constraint on reassignment
--FILE--
<?php
class UnsetReassignExpected
{
    public function value(): string
    {
        return 'expected';
    }
}

class UnsetReassignOther
{
}

function makeUnsetReassignExpected(): UnsetReassignExpected
{
    return new UnsetReassignExpected();
}

function makeUnsetReassignOtherDynamic(): mixed
{
    return new UnsetReassignOther();
}

function makeUnsetReassignNullDynamic(): mixed
{
    return null;
}

function main()
{
    $value = makeUnsetReassignExpected();
    unset($value);

    try {
        $value = makeUnsetReassignOtherDynamic();
        echo "invalid assignment accepted\n";
    } catch (Throwable $error) {
        echo $error::class, "\n";
    }

    try {
        $value = makeUnsetReassignNullDynamic();
        echo "null assignment accepted\n";
    } catch (Throwable $error) {
        echo $error::class, "\n";
    }

    try {
        $value = makeUnsetReassignOtherDynamic();
        echo "invalid assignment after null accepted\n";
    } catch (Throwable $error) {
        echo $error::class, "\n";
    }

    $value = makeUnsetReassignExpected();
    var_dump($value->value());
}
?>
--EXPECT--
TypeError
null assignment accepted
TypeError
string(8) "expected"
