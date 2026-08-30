--TEST--
FiberGenerator cannot be directly constructed cloned or serialized
--FILE--
<?php
function guarded_generator(): iterable
{
    yield 1;
}

function main(): void
{
    $generator = guarded_generator();
    $generator->current();

    foreach (['clone', 'serialize', 'construct'] as $operation) {
        try {
            if ($operation === 'clone') {
                $copy = clone $generator;
            } elseif ($operation === 'serialize') {
                serialize($generator);
            } else {
                new FiberGenerator(null);
            }
            echo $operation, ":allowed\n";
        } catch (Throwable $e) {
            echo $operation, ':', get_class($e), "\n";
        }
    }
}
?>
--EXPECT--
clone:Error
serialize:Exception
construct:Error
