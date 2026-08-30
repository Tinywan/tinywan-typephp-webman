--TEST--
Magic methods with implicit return types keep distinct Zend arginfo
--FILE--
<?php

class ImplicitMagicReturnTypes
{
    private array $values = ['answer' => 42];

    public function render()
    {
        return 'rendered';
    }

    public function __toString()
    {
        return $this->render();
    }

    /**
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->values[$name]);
    }

    /**
     * @return void
     */
    public function __unset($name)
    {
        unset($this->values[$name]);
    }

    public function __set($name, $value)
    {
        $this->values[$name] = $value;
    }

    public function __serialize()
    {
        return $this->values;
    }

    public function __unserialize($data)
    {
        $this->values = $data;
    }
}

function returnType(string $method): string
{
    $type = (new ReflectionMethod(ImplicitMagicReturnTypes::class, $method))->getReturnType();
    return $type ? (string) $type : 'none';
}

function main(): void
{
    $value = new ImplicitMagicReturnTypes();

    var_dump((string) $value);

    foreach (['__toString', '__isset', '__unset', '__set', '__serialize', '__unserialize'] as $method) {
        echo $method, ': ', returnType($method), PHP_EOL;
    }
}
?>
--EXPECT--
string(8) "rendered"
__toString: string
__isset: bool
__unset: void
__set: void
__serialize: array
__unserialize: void
