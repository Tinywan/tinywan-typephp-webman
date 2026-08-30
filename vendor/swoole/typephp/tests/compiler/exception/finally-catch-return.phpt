--TEST--
finally runs for return inside catch without changing return value
--FILE--
<?php

class FinallyCatchReturnException extends Exception {}

function catch_finally_return(): string
{
    $state = "before";
    try {
        throw new FinallyCatchReturnException("failure");
    } catch (FinallyCatchReturnException $e) {
        $state = "catch:" . $e->getMessage();
        return $state;
    } finally {
        echo "finally:$state\n";
        $state = "finally";
    }
}

function main(): void
{
    var_dump(catch_finally_return());
}
?>
--EXPECT--
finally:catch:failure
string(13) "catch:failure"
