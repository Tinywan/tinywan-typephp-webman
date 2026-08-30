--TEST--
Coalesce and shorthand ternary evaluate right side lazily
--FILE--
<?php
function makeArgs(string $name): array
{
    echo "args:$name\n";
    return [$name];
}

function makeValue(string $name): string
{
    echo "value:$name\n";
    return $name;
}

function build(string $id, string $value): string
{
    echo "body:$id:$value\n";
    return $id . ':' . $value;
}

function main(): void
{
    $present = 'left';
    var_dump($present ?? build(...makeArgs('coalesce-skip'), value: makeValue('coalesce-skip')));

    $missing = null;
    var_dump($missing ?? build(...makeArgs('coalesce-run'), value: makeValue('coalesce-run')));

    $truthy = 'truthy';
    var_dump($truthy ?: build(...makeArgs('shorthand-skip'), value: makeValue('shorthand-skip')));

    $empty = '';
    var_dump($empty ?: build(...makeArgs('shorthand-run'), value: makeValue('shorthand-run')));
}
?>
--EXPECT--
string(4) "left"
args:coalesce-run
value:coalesce-run
body:coalesce-run:coalesce-run
string(25) "coalesce-run:coalesce-run"
string(6) "truthy"
args:shorthand-run
value:shorthand-run
body:shorthand-run:shorthand-run
string(27) "shorthand-run:shorthand-run"
