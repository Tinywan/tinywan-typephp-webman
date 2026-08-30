--TEST--
class entry registration resolves cross-namespace interface dependencies before implementors
--FILE--
<?php

namespace RegistrationConsumer {
    use RegistrationContracts\BaseContract;

    interface ChildContract extends BaseContract
    {
    }

    class Implementation implements ChildContract
    {
    }
}

namespace RegistrationContracts {
    interface BaseContract
    {
    }
}

namespace {
    function main(): void
    {
        $value = new RegistrationConsumer\Implementation();
        var_dump($value instanceof RegistrationConsumer\ChildContract);
        var_dump($value instanceof RegistrationContracts\BaseContract);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
