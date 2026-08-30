--TEST--
function attributes are available through reflection
--FILE--
<?php

#[Attribute(Attribute::TARGET_FUNCTION)]
class AotFunctionMeta
{
    public function __construct(public string $name, public int $priority = 0)
    {
    }
}

#[AotFunctionMeta('handler', 10)]
function attributed_function(): string
{
    return 'ok';
}

function main(): void
{
    $func = new ReflectionFunction('attributed_function');
    $attrs = $func->getAttributes(AotFunctionMeta::class);

    var_dump($attrs[0]->getName());
    var_dump($attrs[0]->getArguments());
    var_dump($attrs[0]->newInstance()->name);
    var_dump(attributed_function());
}
?>
--EXPECT--
string(15) "AotFunctionMeta"
array(2) {
  [0]=>
  string(7) "handler"
  [1]=>
  int(10)
}
string(7) "handler"
string(2) "ok"
