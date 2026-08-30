--TEST--
Alternative control structure syntax shares normal control-flow semantics
--FILE--
<?php

function main(): void
{
    $events = [];
    $value = 2;

    if ($value === 1):
        $events[] = 'if:one';
    elseif ($value === 2):
        $events[] = 'if:two';
    else:
        $events[] = 'if:other';
    endif;

    for ($i = 0; $i < 4; $i++):
        if ($i === 1):
            continue;
        endif;
        $events[] = 'for:' . $i;
    endfor;

    $i = 0;
    while ($i < 2):
        $events[] = 'while:' . $i;
        $i++;
    endwhile;

    foreach (['a' => 1, 'b' => 2] as $key => $item):
        $events[] = 'foreach:' . $key . '=' . $item;
    endforeach;

    switch ($value):
        case 1:
            $events[] = 'switch:one';
            break;
        case 2:
            $events[] = 'switch:two';
            break;
        default:
            $events[] = 'switch:other';
            break;
    endswitch;

    echo implode('|', $events), "\n";
}
?>
--EXPECT--
if:two|for:0|for:2|for:3|while:0|while:1|foreach:a=1|foreach:b=2|switch:two
