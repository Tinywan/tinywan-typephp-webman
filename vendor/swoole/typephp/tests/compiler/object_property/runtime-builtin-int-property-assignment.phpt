--TEST--
Runtime-dispatched builtin int results support typed property assignments
--FILE--
<?php

namespace RuntimeBuiltinNamespace {

class RuntimeBuiltinIntProperty
{
    public int $value = 0;

    public function assign(): void
    {
        $this->value = connection_status();
    }

    public function add(): void
    {
        $this->value += connection_status();
    }

    public function addViaLocal(): void
    {
        $status = connection_status();
        $this->value += $status;
    }
}

}

namespace {

function main(): void
{
    $value = new RuntimeBuiltinNamespace\RuntimeBuiltinIntProperty();
    $value->assign();
    var_dump($value->value);
    $value->add();
    var_dump($value->value);
    $value->addViaLocal();
    var_dump($value->value);
}

}
?>
--EXPECT--
int(0)
int(0)
int(0)
