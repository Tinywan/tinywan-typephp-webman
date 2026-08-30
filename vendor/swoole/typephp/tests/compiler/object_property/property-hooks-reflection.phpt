--TEST--
PHP 8.4 property hooks expose Zend reflection metadata
--FILE--
<?php

final class ReflectedPropertyHooks
{
    private string $stored = 'initial';

    public string $virtual {
        final get => 'value';
        set {
        }
    }

    public string $finalSetter {
        get => $this->stored;
        final set {
            $this->stored = $value;
        }
    }
}

function main(): void
{
    $property = new ReflectionProperty(ReflectedPropertyHooks::class, 'virtual');
    var_dump($property->hasHooks());
    var_dump($property->isVirtual());
    foreach ($property->getHooks() as $kind => $hook) {
        echo $kind, ':', $hook->getName(), ':', $hook->isFinal() ? 'final' : 'not-final', "\n";
    }

    $object = new ReflectedPropertyHooks();
    $object->finalSetter = 'updated';
    var_dump($object->finalSetter);

    $setterProperty = new ReflectionProperty(ReflectedPropertyHooks::class, 'finalSetter');
    foreach ($setterProperty->getHooks() as $kind => $hook) {
        echo 'finalSetter-', $kind, ':', $hook->isFinal() ? 'final' : 'not-final', "\n";
    }
}
?>
--EXPECT--
bool(true)
bool(true)
get:$virtual::get:final
set:$virtual::set:not-final
string(7) "updated"
finalSetter-get:not-final
finalSetter-set:final
