--TEST--
typed object accepts null as its empty state and retains its declared class constraint
--FILE--
<?php
class UnsetTypedObjectValue
{
    public int $number = 1;

    public function value(): string
    {
        return 'value';
    }

    public function readProperty(): int
    {
        return $this->number;
    }
}

function makeUnsetTypedObjectValue(): UnsetTypedObjectValue
{
    return new UnsetTypedObjectValue();
}

function main()
{
    $value = makeUnsetTypedObjectValue();
    var_dump(($value = null));

    var_dump($value === null);
    var_dump($value instanceof UnsetTypedObjectValue);
    var_dump(isset($value));

    try {
        @$value->readProperty();
    } catch (Throwable $error) {
        echo $error::class, "\n";
    }

    $value = makeUnsetTypedObjectValue();
    var_dump($value->value());

    unset($value);
    $value = null;
    var_dump($value);

    $value = makeUnsetTypedObjectValue();
    var_dump($value->readProperty());
}
?>
--EXPECT--
NULL
bool(true)
bool(false)
bool(false)
Error
string(5) "value"
NULL
int(1)
