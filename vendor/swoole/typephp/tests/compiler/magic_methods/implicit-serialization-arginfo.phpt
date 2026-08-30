--TEST--
Implicit serialization magic method return types are not aliased by arginfo deduplication
--FILE--
<?php

class ImplicitSerializationReturnTypes
{
    public function noReturnType()
    {
        return null;
    }

    public function __serialize()
    {
        return ['value' => 7];
    }

    public function __unserialize($data)
    {
    }

    public function __sleep()
    {
        return [];
    }

    public function __wakeup()
    {
    }
}

function serializationReturnType(string $method): string
{
    $type = (new ReflectionMethod(ImplicitSerializationReturnTypes::class, $method))->getReturnType();
    return $type ? (string) $type : 'none';
}

function main(): void
{
    foreach (['noReturnType', '__serialize', '__unserialize', '__sleep', '__wakeup'] as $method) {
        echo $method, ': ', serializationReturnType($method), PHP_EOL;
    }

    $serialized = serialize(new ImplicitSerializationReturnTypes());
    var_dump(str_contains($serialized, 'value'));
}
?>
--EXPECT--
noReturnType: none
__serialize: array
__unserialize: void
__sleep: array
__wakeup: void
bool(true)
