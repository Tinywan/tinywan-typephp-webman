--TEST--
method_exists and property_exists with dynamic names
--FILE--
<?php

class ExistsDynamicTarget
{
    public int $count = 1;
    private string $secret = 'x';

    public function run(): string
    {
        return 'run';
    }
}

function choose_name(string $kind): string
{
    echo "choose:$kind\n";
    return $kind === 'method' ? 'run' : 'secret';
}

function main(): void
{
    $object = new ExistsDynamicTarget();
    $class = ExistsDynamicTarget::class;

    var_dump(method_exists($object, choose_name('method')));
    var_dump(method_exists($class, 'missing'));
    var_dump(property_exists($object, choose_name('property')));
    var_dump(property_exists($class, 'count'));
}
?>
--EXPECT--
choose:method
bool(true)
bool(false)
choose:property
bool(true)
bool(true)
