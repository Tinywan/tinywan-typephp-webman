--TEST--
static closure created in an instance method remains a valid delayed callback
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

final class CallbackHolder
{
    private Closure $callback;

    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function invoke(): mixed
    {
        return ($this->callback)();
    }
}

final class CallbackFactory
{
    public function create(): CallbackHolder
    {
        return new CallbackHolder(
            static function (): never {
                throw new LogicException('expected callback');
            },
        );
    }
}

function main(): void
{
    $callback = (new CallbackFactory())->create();

    try {
        $callback->invoke();
    } catch (LogicException $error) {
        echo $error->getMessage(), "\n";
    }
}
?>
--EXPECT--
expected callback
