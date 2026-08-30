--TEST--
Reference to a typed static property preserves its type constraint
--FILE--
<?php
use native_types;

class TypedStaticReference
{
    public static int $value = 1;
}

function main(): void
{
    $reference = &TypedStaticReference::$value;
    try {
        $reference = 'invalid';
    } catch (TypeError $error) {
        echo "TypeError\n";
    }
    var_dump(TypedStaticReference::$value);
}
?>
--EXPECT--
TypeError
int(1)
