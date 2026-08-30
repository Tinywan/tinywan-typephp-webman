--TEST--
For condition and loop expressions evaluate side effects at the correct time
--FILE--
<?php
function makeArgs(string $name): array
{
    echo "args:$name\n";
    return [$name];
}

function keepGoing(string $id, int $value): bool
{
    echo "cond:$id:$value\n";
    return $value < 3;
}

function step(string $id, int $value): void
{
    global $i;
    echo "step:$id:$value\n";
    $i++;
}

function main(): void
{
    global $i;
    for (
        $i = 0;
        keepGoing(...makeArgs('cond-' . $i), value: $i);
        step(...makeArgs('step-' . $i), value: $i)
    ) {
        echo "body:$i\n";
        if ($i === 1) {
            continue;
        }
    }
}
?>
--EXPECT--
args:cond-0
cond:cond-0:0
body:0
args:step-0
step:step-0:0
args:cond-1
cond:cond-1:1
body:1
args:step-1
step:step-1:1
args:cond-2
cond:cond-2:2
body:2
args:step-2
step:step-2:2
args:cond-3
cond:cond-3:3
