--TEST--
closure typed argument with catch chain and dynamic instanceof
--ENV--
USE_ZEND_ALLOC=0
--FILE--
<?php

class ExpectedClosureException extends Exception {}
class ExpectedClosureError extends Error {}

function catches_expected(Closure $expression, string $class): bool
{
    $actual = 'none';

    try {
        $expression();
    } catch (Exception $e) {
        $actual = get_class($e);
        if ($e instanceof $class) {
            echo "matched-exception:$actual\n";
            return true;
        }
    } catch (Throwable $e) {
        $actual = get_class($e);
        if ($e instanceof $class) {
            echo "matched-throwable:$actual\n";
            return true;
        }
    }

    echo "miss:$actual\n";
    return false;
}

function main(): void
{
    var_dump(catches_expected(function () {
        throw new ExpectedClosureException('ok');
    }, ExpectedClosureException::class));

    var_dump(catches_expected(function () {
        throw new ExpectedClosureError('err');
    }, ExpectedClosureError::class));

    var_dump(catches_expected(function () {
        throw new Exception('base');
    }, ExpectedClosureException::class));
}
?>
--EXPECT--
matched-exception:ExpectedClosureException
bool(true)
matched-throwable:ExpectedClosureError
bool(true)
miss:Exception
bool(false)
