--TEST--
Loop counter narrowing rejects variables previously assigned by foreach
--FILE--
<?php

function main(): void
{
    foreach (['name' => 10, 20 => 30] as $i => $value) {
        var_dump($i, $value);
    }

    for ($i = 0; $i < 2; $i++) {
        var_dump($i);
    }

    foreach (['text'] as $i) {
        var_dump($i);
    }

    for ($i = 0; $i < 1; $i++) {
        var_dump($i);
    }

    foreach ([['nested']] as [$i]) {
        var_dump($i);
    }

    for ($i = 0; $i < 1; $i++) {
        var_dump($i);
    }
}
?>
--EXPECT--
string(4) "name"
int(10)
int(20)
int(30)
int(0)
int(1)
string(4) "text"
int(0)
string(6) "nested"
int(0)
