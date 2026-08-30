--TEST--
finally must not see variables from unmatched catch clauses
--FILE--
<?php

class FinallyUnmatchedA extends Exception {}
class FinallyUnmatchedB extends Exception {}

function main(): void
{
    try {
        try {
            throw new FinallyUnmatchedA("a");
        } catch (FinallyUnmatchedB $e) {
            echo "catch-b\n";
        } finally {
            echo isset($e) ? "set\n" : "unset\n";
        }
    } catch (FinallyUnmatchedA $e) {
        echo "outer:" . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
unset
outer:a
