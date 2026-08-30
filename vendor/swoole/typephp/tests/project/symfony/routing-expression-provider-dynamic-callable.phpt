--TEST--
Symfony Routing pattern: provider returns callable invoked with unpacked args
--FILE--
<?php

class FunctionProvider
{
    /** @var array<string, callable> */
    private array $functions = [];

    public function set(string $name, callable $function): void
    {
        $this->functions[$name] = $function;
    }

    public function get(string $name): callable
    {
        return $this->functions[$name];
    }
}

function evaluate(FunctionProvider $provider, string $function, array $args): string
{
    return $provider->get($function)(...$args);
}

function main(): void
{
    $provider = new FunctionProvider();
    $provider->set('format', static fn (string $prefix, string $value): string => $prefix.':'.strtoupper($value));

    var_dump(evaluate($provider, 'format', ['name', 'symfony']));
}
?>
--EXPECT--
string(12) "name:SYMFONY"
