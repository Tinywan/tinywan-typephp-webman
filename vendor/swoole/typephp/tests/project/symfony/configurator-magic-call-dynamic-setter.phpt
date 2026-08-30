--TEST--
Symfony pattern: __call forwards to concatenated dynamic setter with unpack
--FILE--
<?php

final class Configurator
{
    private array $values = [];

    public function __call(string $method, array $args): mixed
    {
        if (method_exists($this, 'set'.$method)) {
            return $this->{'set'.$method}(...$args);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method "%s::%s()".', static::class, $method));
    }

    private function setOption(string $name, mixed $value, bool $append = false): static
    {
        if ($append) {
            $this->values[$name][] = $value;
        } else {
            $this->values[$name] = $value;
        }

        return $this;
    }

    public function all(): array
    {
        return $this->values;
    }
}

function main(): void
{
    $configurator = new Configurator();
    $configurator->Option('debug', true);
    $configurator->Option('tags', 'console', true);
    $configurator->Option(...['tags', 'worker', true]);

    var_dump($configurator->all());
}
?>
--EXPECT--
array(2) {
  ["debug"]=>
  bool(true)
  ["tags"]=>
  array(2) {
    [0]=>
    string(7) "console"
    [1]=>
    string(6) "worker"
  }
}
