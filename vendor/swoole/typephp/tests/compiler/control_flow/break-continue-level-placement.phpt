--TEST--
Multi-level break/continue must skip trailing statements of enclosing bodies
--FILE--
<?php

function nativeSwitchBreak(int $n): void
{
    // break 2 from a native (int-typed) switch inside a loop must exit the loop
    for ($i = 0; $i < 3; $i++) {
        echo "n-iter $i\n";
        switch ($n) {
            case 1:
                echo "n-case\n";
                break 2;
            default:
                break;
        }
        echo "n-after $i\n";
    }
    echo "native-switch-break-2: done\n";
}

function main(): void
{
    // break 2: statements after the inner loop must not run
    foreach ([1, 2, 3] as $x) {
        foreach ([1, 2, 3] as $y) {
            echo "b2 inner $x.$y\n";
            break 2;
        }
        echo "b2 leaked $x\n";
    }
    echo "break-2: done\n";

    // continue 2: statements after the inner loop must not run
    foreach ([1, 2] as $x) {
        foreach ([1, 2] as $y) {
            echo "c2 inner $x.$y\n";
            continue 2;
        }
        echo "c2 leaked $x\n";
    }
    echo "continue-2: done\n";

    // break 2 from a switch inside a loop: statements after the switch
    // must not run and the loop must exit
    for ($i = 0; $i < 3; $i++) {
        echo "sw iter $i\n";
        switch ($i) {
            case 1:
                echo "sw case $i\n";
                break 2;
            default:
                break;
        }
        echo "sw after $i\n";
    }
    echo "switch-break-2: done\n";

    // continue 2 from a switch inside a loop targets the loop
    for ($i = 0; $i < 3; $i++) {
        switch ($i) {
            case 1:
                echo "swc case $i\n";
                continue 2;
            default:
                break;
        }
        echo "swc after $i\n";
    }
    echo "switch-continue-2: done\n";

    // break 3 from a loop inside a switch inside a loop
    for ($i = 0; $i < 3; $i++) {
        switch ($i) {
            case 0:
                foreach ([1, 2] as $y) {
                    echo "b3 deep $i.$y\n";
                    break 3;
                }
                echo "b3 leaked after deep loop\n";
                break;
            default:
                echo "b3 leaked default\n";
                break;
        }
        echo "b3 leaked after switch $i\n";
    }
    echo "break-3-through-switch: done\n";

    // continue 2 from a loop inside a switch acts as break on the switch
    // level: statements after the switch must still run
    for ($i = 0; $i < 2; $i++) {
        switch ($i) {
            case 0:
                foreach ([1, 2] as $y) {
                    echo "c2s deep $i.$y\n";
                    continue 2;
                }
                echo "c2s leaked after deep loop\n";
                break;
            default:
                break;
        }
        echo "c2s after switch $i\n";
    }
    echo "continue-2-targets-switch: done\n";

    // continue 3 propagates through a switch up to the outer loop
    for ($i = 0; $i < 2; $i++) {
        switch ($i) {
            case 0:
                foreach ([1, 2] as $y) {
                    echo "c3 deep $i.$y\n";
                    continue 3;
                }
                echo "c3 leaked after deep loop\n";
                break;
            default:
                break;
        }
        echo "c3 after switch $i\n";
    }
    echo "continue-3-through-switch: done\n";

    nativeSwitchBreak(1);
}
?>
--EXPECT--
b2 inner 1.1
break-2: done
c2 inner 1.1
c2 inner 2.1
continue-2: done
sw iter 0
sw after 0
sw iter 1
sw case 1
switch-break-2: done
swc after 0
swc case 1
swc after 2
switch-continue-2: done
b3 deep 0.1
break-3-through-switch: done
c2s deep 0.1
c2s after switch 0
c2s after switch 1
continue-2-targets-switch: done
c3 deep 0.1
c3 after switch 1
continue-3-through-switch: done
n-iter 0
n-case
native-switch-break-2: done
