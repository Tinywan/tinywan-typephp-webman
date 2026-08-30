--TEST--
Native class: typed pointers share object identity without PHP references
--FILE--
<?php

#[Native]
class NativeParameterValue
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }
}

function native_parameter_update(NativeParameterValue $value): void
{
    $value->value = 42;
    // The pointer itself is passed by value. Rebinding this local slot must
    // not clear or replace the caller's pointer.
    $value = null;
}

function native_parameter_nullable(?NativeParameterValue $value): ?NativeParameterValue
{
    return $value;
}

function main(): void
{
    $value = new NativeParameterValue(1);
    $alias = $value;
    $alias->value = 2;
    var_dump($value === $alias, $value->value);
    native_parameter_update($value);
    var_dump($value->value);
    var_dump(native_parameter_nullable($value) === $value);
    $nullable = native_parameter_nullable(null);
    var_dump(isset($nullable));
    var_dump(function_exists('native_parameter_update'));
    var_dump(function_exists('native_parameter_nullable'));
}

?>
--EXPECT--
bool(true)
int(2)
int(42)
bool(true)
bool(false)
bool(false)
bool(false)
