--TEST--
AOT dynamic property assignment TypeError can be caught
--FILE--
<?php
class TypeHitDynamicProperty {
    public int|string $union;
}

function assign_dynamic(TypeHitDynamicProperty $obj, string $prop, mixed $value): void {
    try {
        $obj->$prop = $value;
        var_dump('not reached');
    } catch (TypeError $e) {
        var_dump(get_class($e));
        var_dump(str_contains($e->getMessage(), 'Cannot assign null to property TypeHitDynamicProperty::$union'));
    }
}

function main(): void
{
    $obj = new TypeHitDynamicProperty();
    assign_dynamic($obj, 'union', null);
    $obj->union = 'ok';
    var_dump($obj->union);
}
?>
--EXPECT--
string(9) "TypeError"
bool(true)
string(2) "ok"
