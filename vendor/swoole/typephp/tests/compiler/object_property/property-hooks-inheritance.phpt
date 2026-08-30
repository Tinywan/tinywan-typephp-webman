--TEST--
PHP 8.4 property hooks inherit and override get/set independently
--FILE--
<?php

class ParentHook
{
    protected string $stored = '';

    public string $value {
        get => 'parent:' . $this->stored;
        set {
            $this->stored = 'set:' . $value;
        }
    }

    public function writeFromParent(string $value): void
    {
        $this->value = $value;
    }

    public function readFromParent(): string
    {
        return $this->value;
    }
}

class ChildHook extends ParentHook
{
    public string $value {
        get => 'child:' . $this->stored;
    }
}

class PlainHookChild extends ParentHook
{
    public string $value;
}

function writeHookDynamically(mixed $object, string $value): void
{
    $object->value = $value;
}

function readHookDynamically(mixed $object): string
{
    return $object->value;
}

function main(): void
{
    $child = new ChildHook();

    $child->value = 'direct';
    var_dump($child->value, $child->readFromParent());

    $child->writeFromParent('parent');
    var_dump($child->value, $child->readFromParent());

    writeHookDynamically($child, 'dynamic');
    var_dump(readHookDynamically($child));

    $property = new ReflectionProperty(ChildHook::class, 'value');
    foreach ($property->getHooks() as $kind => $hook) {
        echo $kind, ':', $hook->getDeclaringClass()->getName(), ':', $hook->isFinal() ? 'final' : 'open', "\n";
    }

    $plain = new PlainHookChild();
    $plain->value = 'plain';
    var_dump($plain->value, $plain->readFromParent());
    writeHookDynamically($plain, 'plain-dynamic');
    var_dump(readHookDynamically($plain));

    $plainProperty = new ReflectionProperty(PlainHookChild::class, 'value');
    foreach ($plainProperty->getHooks() as $kind => $hook) {
        echo 'plain-', $kind, ':', $hook->getDeclaringClass()->getName(), "\n";
    }
}
?>
--EXPECT--
string(16) "child:set:direct"
string(16) "child:set:direct"
string(16) "child:set:parent"
string(16) "child:set:parent"
string(17) "child:set:dynamic"
get:ChildHook:open
set:ParentHook:open
string(16) "parent:set:plain"
string(16) "parent:set:plain"
string(24) "parent:set:plain-dynamic"
plain-get:ParentHook
plain-set:ParentHook
