--TEST--
switch default before later matching case
--FILE--
<?php

function main(): void
{
    $value = 'target';

    switch ($value) {
        case 'first':
            echo "first\n";
            break;
        default:
            echo "default\n";
            break;
        case 'target':
            echo "target\n";
            break;
    }
}
?>
--EXPECT--
target
