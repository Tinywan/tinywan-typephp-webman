--TEST--
PHP 8.4 interface property hooks define abstract property contracts
--FILE--
<?php

interface NamedContract
{
    public string $displayName { get; }
}

interface MutableNameContract
{
    public string $displayName { get; set; }
}

interface NameSinkContract
{
    public string $displayName { set; }
}

interface ExtendedNamedContract extends NamedContract
{
}

final class BackedName implements NamedContract
{
    public string $displayName = 'backed';
}

final class HookedName implements NamedContract
{
    public string $displayName {
        get => 'hooked';
    }
}

class InheritedName
{
    public string $displayName = 'inherited';
}

final class MutableName extends InheritedName implements MutableNameContract
{
}

final class ExtendedName implements ExtendedNamedContract
{
    public string $displayName = 'extended';
}

final class NameSink implements NameSinkContract
{
    private string $stored = '';

    public string $displayName {
        set => $this->stored = $value;
    }

    public function stored(): string
    {
        return $this->stored;
    }
}

function main(): void
{
    $backed = new BackedName();
    $hooked = new HookedName();
    echo $backed->displayName, "\n";
    echo $hooked->displayName, "\n";
    $mutable = new MutableName();
    $mutable->displayName = 'changed';
    echo $mutable->displayName, "\n";
    $extended = new ExtendedName();
    echo $extended->displayName, "\n";
    $sink = new NameSink();
    $sink->displayName = 'sink';
    echo $sink->stored(), "\n";

    $property = new ReflectionProperty(NamedContract::class, 'displayName');
    var_dump($property->isAbstract());
    var_dump($property->isVirtual());
    foreach ($property->getHooks() as $kind => $hook) {
        echo $kind, ':', $hook->getName(), ':', $hook->isAbstract() ? 'abstract' : 'concrete', "\n";
    }
    $mutableProperty = new ReflectionProperty(MutableNameContract::class, 'displayName');
    foreach ($mutableProperty->getHooks() as $kind => $hook) {
        echo $kind, ':', $hook->getName(), ':', $hook->isAbstract() ? 'abstract' : 'concrete', "\n";
    }

    eval('final class DynamicName implements NamedContract { public string $displayName = "dynamic"; }');
    $dynamic = new DynamicName();
    echo $dynamic->displayName, "\n";
    eval('final class DynamicMutableName implements MutableNameContract { public string $displayName = "before"; }');
    $dynamicMutable = new DynamicMutableName();
    $dynamicMutable->displayName = 'dynamic-write';
    echo $dynamicMutable->displayName, "\n";
}

?>
--EXPECT--
backed
hooked
changed
extended
sink
bool(true)
bool(true)
get:$displayName::get:abstract
get:$displayName::get:abstract
set:$displayName::set:abstract
dynamic
dynamic-write
