--TEST--
String concat assignment preserves PHP value, COW and expression semantics
--FILE--
<?php
declare(strict_types=1);

final class ConcatAssignStringable
{
    public function __toString(): string
    {
        echo "convert\n";
        return 'object';
    }
}

function main(): void
{
    $value = 'start';
    $copy = $value;
    $suffix = 'tail';

    $value .= ':';
    $value .= $suffix . ':' . new ConcatAssignStringable();
    var_dump($value, $copy);

    $self = 'ab';
    $self .= $self;
    var_dump($self);

    $result = ($value .= '!');
    var_dump($value, $result);
}
?>
--EXPECT--
convert
string(17) "start:tail:object"
string(5) "start"
string(4) "abab"
string(18) "start:tail:object!"
string(18) "start:tail:object!"
