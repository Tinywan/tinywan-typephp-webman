--TEST--
finally runs for return nested in if/else branches without changing return value
--FILE--
<?php

function nested_finally_return(int $value): string
{
    $state = "start";
    try {
        if ($value > 0) {
            $state .= ":positive";
            return $state;
        } else {
            $state .= ":negative";
            return $state;
        }
    } finally {
        echo "finally:$state\n";
        $state .= ":finally";
    }
}

function main(): void
{
    var_dump(nested_finally_return(1));
    var_dump(nested_finally_return(-1));
}
?>
--EXPECT--
finally:start:positive
string(14) "start:positive"
finally:start:negative
string(14) "start:negative"
