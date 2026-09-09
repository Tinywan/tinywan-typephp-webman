--TEST--
expected and unexpected provide branch prediction hints without changing condition semantics
--FILE--
<?php

function predicted_condition(int &$calls, bool $result): bool
{
    $calls++;
    return $result;
}

function main(): void
{
    $calls = std::any(0);

    if (std::expected(predicted_condition($calls, true))) {
        echo "expected\n";
    }

    if (std::unexpected(predicted_condition($calls, false))) {
        echo "unexpected-true\n";
    } else {
        echo "unexpected-false\n";
    }

    if (\std::expected(condition: predicted_condition($calls, true))) {
        echo "fully-qualified\n";
    }

    var_dump(std::expected(1), std::unexpected(0), $calls);
}
?>
--EXPECT--
expected
unexpected-false
fully-qualified
bool(true)
bool(false)
int(3)
