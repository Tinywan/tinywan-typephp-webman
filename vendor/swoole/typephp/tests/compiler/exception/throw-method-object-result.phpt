--TEST--
throw accepts object-valued method call results
--FILE--
<?php

class ExceptionFactory
{
    private function typedException(): LogicException
    {
        return new LogicException('typed');
    }

    private function objectException(): object
    {
        return new RuntimeException('object');
    }

    private function nonThrowable(): stdClass
    {
        return new stdClass();
    }

    public function throwTyped(): void
    {
        throw $this->typedException();
    }

    public function throwObject(): void
    {
        throw $this->objectException();
    }

    public function throwNonThrowable(): void
    {
        throw $this->nonThrowable();
    }
}

function main(): void
{
    $factory = new ExceptionFactory();
    try {
        $factory->throwTyped();
    } catch (Throwable $e) {
        echo get_class($e), ':', $e->getMessage(), "\n";
    }

    try {
        $factory->throwObject();
    } catch (Throwable $e) {
        echo get_class($e), ':', $e->getMessage(), "\n";
    }

    try {
        $factory->throwNonThrowable();
    } catch (Error $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
LogicException:typed
RuntimeException:object
Cannot throw objects that do not implement Throwable
