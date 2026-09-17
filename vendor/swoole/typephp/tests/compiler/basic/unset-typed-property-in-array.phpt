--TEST--
unset fixed scalar and array properties of objects stored in arrays restores empty values
--FILE--
<?php

class ArrayElementWithTypedProperties {
    public int $number = 9;
    public float $fraction = 1.5;
    public bool $enabled = true;
    public string $label = 'initial';
    public array $values = [1, 2];
}

function main(): void {
    $objects = [new ArrayElementWithTypedProperties()];
    $nested = ['group' => $objects];

    unset($objects[0]->number);
    unset($objects[0]->fraction);
    unset($objects[0]->enabled);
    unset($nested['group'][0]->label);
    unset($nested['group'][0]->values);

    var_dump($objects[0]->number, $objects[0]->fraction, $objects[0]->enabled);
    var_dump($objects[0]->label, $objects[0]->values);
    var_dump(isset($objects[0]->number), isset($objects[0]->label));
}
?>
--EXPECT--
int(0)
float(0)
bool(false)
string(0) ""
array(0) {
}
bool(true)
bool(true)
