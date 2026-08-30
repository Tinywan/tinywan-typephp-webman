--TEST--
Native class: Getter and Setter generators lower to direct native methods
--FILE--
<?php

#[Native]
class NativeGeneratedAccessors
{
    #[Getter]
    #[Setter]
    private int $value = 1;

    public function __construct(
        #[Getter]
        private string $name = 'native',
    ) {
    }
}

function main(): void
{
    $object = new NativeGeneratedAccessors();
    var_dump($object->getValue());
    var_dump($object->getName());
    $object->setValue(42);
    var_dump($object->getValue());
}

?>
--EXPECT--
int(1)
string(6) "native"
int(42)
