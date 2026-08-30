--TEST--
Nullsafe method call does not evaluate arguments when receiver is null
--FILE--
<?php
class Recorder
{
    public function set(int $id, string $value): string
    {
        return $id . ':' . $value;
    }
}

function makeArgs(): array
{
    echo "makeArgs\n";
    return [7];
}

function makeValue(): string
{
    echo "makeValue\n";
    return "ok";
}

function main(): void
{
    $null = null;
    var_dump($null?->set(...makeArgs(), value: makeValue()));

    $recorder = new Recorder();
    var_dump($recorder?->set(...makeArgs(), value: makeValue()));
}
?>
--EXPECT--
NULL
makeArgs
makeValue
string(4) "7:ok"
