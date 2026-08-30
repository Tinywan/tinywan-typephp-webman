--TEST--
TypePHP destructor exceptions remain inside the Zend wrapper boundary
--FILE--
<?php

final class ThrowingDestructor
{
    public function __destruct()
    {
        throw new RuntimeException('destructor');
    }
}

function main(): void
{
    try {
        $value = new ThrowingDestructor();
        unset($value);
    } catch (RuntimeException $exception) {
        echo $exception->getMessage(), "\n";
    }

    echo "continued\n";
}
?>
--EXPECT--
destructor
continued
