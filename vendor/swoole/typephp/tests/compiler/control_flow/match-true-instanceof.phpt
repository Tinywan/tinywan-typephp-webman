--TEST--
match true with instanceof conditions
--FILE--
<?php

class MatchTrueBase {}
class MatchTrueChild extends MatchTrueBase {}

function describe_value(mixed $value): string
{
    return match (true) {
        $value instanceof MatchTrueChild => 'child',
        $value instanceof MatchTrueBase => 'base',
        is_string($value) => 'string:' . $value,
        default => 'other',
    };
}

function main(): void
{
    var_dump(describe_value(new MatchTrueChild()));
    var_dump(describe_value(new MatchTrueBase()));
    var_dump(describe_value('x'));
    var_dump(describe_value(42));
}
?>
--EXPECT--
string(5) "child"
string(4) "base"
string(8) "string:x"
string(5) "other"
