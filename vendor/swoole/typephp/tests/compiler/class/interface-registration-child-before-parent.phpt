--TEST--
class entry registration preserves a child interface dependency declared before its parent
--FILE--
<?php

namespace CacheContracts {
    interface InvalidArgumentException extends CacheException
    {
    }

    interface CacheException extends \Throwable
    {
    }

    class ConcreteException extends \InvalidArgumentException implements InvalidArgumentException
    {
    }
}

namespace {
    function main(): void
    {
        $exception = new CacheContracts\ConcreteException('invalid cache key');
        var_dump($exception instanceof CacheContracts\InvalidArgumentException);
        var_dump($exception instanceof CacheContracts\CacheException);
        var_dump($exception instanceof Throwable);
        echo $exception->getMessage(), "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
invalid cache key
