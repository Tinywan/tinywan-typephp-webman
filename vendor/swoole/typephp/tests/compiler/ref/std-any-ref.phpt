--TEST--
std::any() and std::ref() compile-time functions
--FILE--
<?php
class RefBox
{
    public string $value = 'object';
}

function main(): void
{
    $native = std::int(10);
    $mixed = \STD::AnY($native);
    var_dump($mixed / 4);

    $replace = static function (mixed &$value): void {
        $value = 'changed';
    };

    $value = std::any('variable');
    $replace(StD::ReF($value));
    echo $value, "\n";

    $values = ['item' => 'array'];
    $replace(std::ref($values['item']));
    echo $values['item'], "\n";

    $box = new RefBox();
    $replace(\std::ref($box->value));
    echo $box->value, "\n";
}
?>
--EXPECT--
float(2.5)
changed
changed
changed
