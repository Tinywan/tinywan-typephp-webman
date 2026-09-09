--TEST--
Former compile-time global names remain available to user functions
--FILE--
<?php
function any(string $value): string
{
    return 'user-any:' . $value;
}

function refval(string $value): string
{
    return 'user-refval:' . $value;
}

function expected(string $value): string
{
    return 'user-expected:' . $value;
}

function unexpected(string $value): string
{
    return 'user-unexpected:' . $value;
}

function objval(string $value): string
{
    return 'user-objval:' . $value;
}

function main(): void
{
    echo any('value'), "\n";
    echo refval('value'), "\n";
    echo expected('value'), "\n";
    echo unexpected('value'), "\n";
    echo objval('value'), "\n";
}
?>
--EXPECT--
user-any:value
user-refval:value
user-expected:value
user-unexpected:value
user-objval:value
