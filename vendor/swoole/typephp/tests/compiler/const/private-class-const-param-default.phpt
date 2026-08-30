--TEST--
Private and protected class constants are valid method parameter defaults in declaration scope
--FILE--
<?php

class PrivateConstDefaults
{
    private const VALUE = 42;
    private const LABEL = 'private';

    public function show(
        int $value = self::VALUE,
        string $label = PrivateConstDefaults::LABEL,
        string $suffix = 'default',
    ): void {
        var_dump($value, $label, $suffix);
    }
}

class ProtectedConstParent
{
    protected const VALUE = 7;
}

class ProtectedConstChild extends ProtectedConstParent
{
    public function show(int $value = parent::VALUE, string $suffix = 'default'): void
    {
        var_dump($value, $suffix);
    }
}

function main(): void
{
    $private = new PrivateConstDefaults();
    $private->show();
    $private->show(suffix: 'named');

    $protected = new ProtectedConstChild();
    $protected->show();
    $protected->show(suffix: 'named');
}
?>
--EXPECT--
int(42)
string(7) "private"
string(7) "default"
int(42)
string(7) "private"
string(5) "named"
int(7)
string(7) "default"
int(7)
string(5) "named"
