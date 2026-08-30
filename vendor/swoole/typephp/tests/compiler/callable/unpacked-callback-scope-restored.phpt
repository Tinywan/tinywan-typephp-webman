--TEST--
Unpacked callback user-code scope is restored after an exception
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

class UnpackedCallbackScopeTarget
{
    private function fail(): void
    {
        throw new RuntimeException('expected');
    }

    public function callback(): array
    {
        return [$this, 'fail'];
    }

    public function run(): void
    {
        $arguments = [[$this, 'fail']];
        call_user_func(...$arguments);
    }
}

function main(): void
{
    $object = new UnpackedCallbackScopeTarget();
    $callback = $object->callback();
    try {
        $object->run();
    } catch (RuntimeException $exception) {
        echo $exception->getMessage(), "\n";
    }
    var_dump(is_callable($callback));
}

?>
--EXPECT--
expected
bool(false)
