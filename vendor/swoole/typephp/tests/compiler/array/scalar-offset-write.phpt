--TEST--
Dynamic scalar offset writes fail instead of being silently ignored
--FILE--
<?php

function expectOffsetWriteError(mixed $value, string $label): void
{
    try {
        $value[] = 'invalid';
        echo $label, ":missing-error\n";
    } catch (Error $error) {
        echo $label, ":error\n";
    }
}

function main(): void
{
    expectOffsetWriteError(1, 'int');
    expectOffsetWriteError(1.5, 'float');
    expectOffsetWriteError(false, 'bool');
    expectOffsetWriteError(new stdClass(), 'object');

    $value = null;
    $value[] = 'valid';
    echo 'null:', $value[0], "\n";
}
?>
--EXPECT--
int:error
float:error
bool:error
object:error
null:valid
