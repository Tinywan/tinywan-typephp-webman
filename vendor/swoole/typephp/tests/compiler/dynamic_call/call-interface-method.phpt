--TEST--
call interface method through concrete object
--FILE--
<?php
interface Runner
{
    public function run(): string;
}

class FastRunner implements Runner
{
    public function run(): string
    {
        return 'fast';
    }
}

function invoke(Runner $runner): string
{
    $alias = $runner;
    return $alias->run();
}

function main(): int
{
    $r = invoke(new FastRunner());
    echo "result: $r\n";
    return $r === 'fast' ? 0 : 1;
}
?>
--EXPECT--
result: fast
