--TEST--
PHP 8.4 final properties preserve runtime and reflection metadata
--FILE--
<?php

class FinalPropertyMetadata
{
    final public string $plain = 'plain';

    final public string $hooked {
        get => 'hooked';
        set {
        }
    }

    final public string $finalHook {
        final get => 'both';
    }

    public private(set) string $privateSet = 'private-set';
}

function main(): void
{
    $object = new FinalPropertyMetadata();
    var_dump($object->plain, $object->hooked, $object->finalHook, $object->privateSet);

    foreach (['plain', 'hooked', 'finalHook', 'privateSet'] as $name) {
        $property = new ReflectionProperty(FinalPropertyMetadata::class, $name);
        echo $name,
            ':final=', $property->isFinal() ? 'yes' : 'no',
            ':hooks=', $property->hasHooks() ? 'yes' : 'no',
            ':virtual=', $property->isVirtual() ? 'yes' : 'no',
            "\n";
        foreach ($property->getHooks() as $kind => $hook) {
            echo $name, '-', $kind, ':', $hook->isFinal() ? 'final' : 'open', "\n";
        }
    }
}
?>
--EXPECT--
string(5) "plain"
string(6) "hooked"
string(4) "both"
string(11) "private-set"
plain:final=yes:hooks=no:virtual=no
hooked:final=yes:hooks=yes:virtual=yes
hooked-get:open
hooked-set:open
finalHook:final=yes:hooks=yes:virtual=yes
finalHook-get:final
privateSet:final=yes:hooks=no:virtual=no
