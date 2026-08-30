--TEST--
AOT multi-catch handles alternative exception types
--FILE--
<?php
class MultiCatchFirst extends Exception {}
class MultiCatchSecond extends Exception {}

function raise_multi(int $kind): void
{
    if ($kind === 1) {
        throw new MultiCatchFirst('first');
    }
    throw new MultiCatchSecond('second');
}

function main(): void
{
    foreach ([1, 2] as $kind) {
        try {
            raise_multi($kind);
        } catch (MultiCatchFirst|MultiCatchSecond $e) {
            var_dump($e->getMessage());
        }
    }
}
?>
--EXPECT--
string(5) "first"
string(6) "second"
