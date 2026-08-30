--TEST--
multi-catch conditions respect catch match state with finally
--FILE--
<?php

class FinallyMultiCatchA extends Exception {}
class FinallyMultiCatchB extends Exception {}
class FinallyMultiCatchC extends Exception {}

function main(): void
{
    try {
        try {
            throw new FinallyMultiCatchA("a");
        } catch (FinallyMultiCatchA|FinallyMultiCatchB $e) {
            echo "catch-ab\n";
            throw new FinallyMultiCatchC("c");
        } catch (FinallyMultiCatchC $e) {
            echo "catch-c\n";
        } finally {
            echo "finally\n";
        }
    } catch (FinallyMultiCatchC $e) {
        echo "outer:" . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
catch-ab
finally
outer:c
