--TEST--
Break and continue with numeric levels
--FILE--
<?php

// break 2 from nested for
for ($i = 0; $i < 3; $i++) {
    for ($j = 0; $j < 3; $j++) {
        if ($i == 1 && $j == 2) {
            break 2;
        }
    }
}
echo "break-2-for: done\n";

// continue 2 from nested for
for ($i = 0; $i < 3; $i++) {
    for ($j = 0; $j < 3; $j++) {
        if ($i == 1 && $j == 1) {
            continue 2;
        }
    }
}
echo "continue-2-for: done\n";

// break 2 from nested while
$i = 0;
while ($i < 3) {
    $j = 0;
    while ($j < 3) {
        if ($i == 1 && $j == 2) {
            break 2;
        }
        $j++;
    }
    $i++;
}
echo "break-2-while: done\n";

// continue 2 from nested while. The counter must advance before the
// inner loop: continue 2 jumps straight to the outer condition, so a
// trailing $i++ would never run and the loop would never terminate
// (PHP itself loops forever on that variant).
$i = 0;
while ($i < 3) {
    $i++;
    $j = 0;
    while ($j < 3) {
        $j++;
        if ($i == 1 && $j == 2) {
            continue 2;
        }
    }
}
echo "continue-2-while: done\n";

// break 2 from nested do-while
$i = 0;
do {
    $j = 0;
    do {
        if ($i == 1 && $j == 2) {
            break 2;
        }
        $j++;
    } while ($j < 3);
    $i++;
} while ($i < 3);
echo "break-2-do-while: done\n";

// continue 3 from triple-nested for
for ($a = 0; $a < 2; $a++) {
    for ($b = 0; $b < 2; $b++) {
        for ($c = 0; $c < 2; $c++) {
            if ($a == 0 && $b == 1 && $c == 0) {
                continue 3;
            }
        }
    }
}
echo "continue-3-for: done\n";

// break 3 from triple-nested for
for ($a = 0; $a < 2; $a++) {
    for ($b = 0; $b < 2; $b++) {
        for ($c = 0; $c < 2; $c++) {
            if ($a == 0 && $b == 1 && $c == 0) {
                break 3;
            }
        }
    }
}
echo "break-3-for: done\n";

// break 2 from switch inside for
for ($i = 0; $i < 3; $i++) {
    switch ($i) {
        case 1:
            break 2;
        default:
            break;
    }
}
echo "break-2-switch: done\n";

?>
--EXPECT--
break-2-for: done
continue-2-for: done
break-2-while: done
continue-2-while: done
break-2-do-while: done
continue-3-for: done
break-3-for: done
break-2-switch: done
