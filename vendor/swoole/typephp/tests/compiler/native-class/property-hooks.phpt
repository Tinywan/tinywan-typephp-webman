--TEST--
Native class: PHP 8.4 property hooks use native getter and setter calls
--FILE--
<?php

#[Native]
class NativeHookValue
{
    private int $stored = 1;

    public int $value {
        get {
            return $this->stored * 2;
        }
        set(int $value) {
            $this->stored = $value;
        }
    }
}

function main(): void
{
    $object = new NativeHookValue();
    var_dump($object->value);
    $object->value = 21;
    var_dump($object->value);
}

?>
--EXPECT--
int(2)
int(42)
