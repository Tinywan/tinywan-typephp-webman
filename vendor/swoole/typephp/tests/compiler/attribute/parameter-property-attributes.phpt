--TEST--
function parameter and property attributes are available through reflection
--FILE--
<?php

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class AotMeta
{
    public function __construct(public string $name, public int $order = 0)
    {
    }
}

class AttributeHolder
{
    #[AotMeta('property', 1)]
    public string $value = 'ok';
}

function attributed_parameter(#[AotMeta('parameter', 2)] string $value): string
{
    return $value;
}

function main(): void
{
    $func = new ReflectionFunction('attributed_parameter');
    $paramAttrs = $func->getParameters()[0]->getAttributes(AotMeta::class);
    var_dump($paramAttrs[0]->getName());
    var_dump($paramAttrs[0]->getArguments());

    $prop = new ReflectionProperty(AttributeHolder::class, 'value');
    $propAttrs = $prop->getAttributes(AotMeta::class);
    var_dump($propAttrs[0]->getName());
    var_dump($propAttrs[0]->getArguments());
}
?>
--EXPECT--
string(7) "AotMeta"
array(2) {
  [0]=>
  string(9) "parameter"
  [1]=>
  int(2)
}
string(7) "AotMeta"
array(2) {
  [0]=>
  string(8) "property"
  [1]=>
  int(1)
}
