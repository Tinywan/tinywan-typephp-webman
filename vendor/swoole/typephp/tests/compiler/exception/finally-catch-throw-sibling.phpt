--TEST--
exception thrown inside catch is not handled by sibling catch
--FILE--
<?php

class FinallySiblingA extends Exception {}
class FinallySiblingB extends Exception {}

function main(): void
{
    try {
        try {
            throw new FinallySiblingA("a");
        } catch (FinallySiblingA $e) {
            echo "catch-a\n";
            throw new FinallySiblingB("b");
        } catch (FinallySiblingB $e) {
            echo "catch-b\n";
        } finally {
            echo "finally\n";
        }
    } catch (FinallySiblingB $e) {
        echo "outer:" . $e->getMessage() . "\n";
    }
}
?>
--EXPECT--
catch-a
finally
outer:b
