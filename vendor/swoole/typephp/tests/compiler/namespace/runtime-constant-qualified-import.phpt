--TEST--
Qualified and imported runtime constants use their exact resolved names
--FILE--
<?php

namespace RuntimeConstantConsumer {
    use const RuntimeConstantProvider\IMPORTED_VALUE as IMPORTED;
    use RuntimeConstantProvider\CLASS_ALIAS_COLLISION;

    function readImported()
    {
        return IMPORTED;
    }

    function readQualified()
    {
        return Config\PATH;
    }

    function readClassAliasCollision()
    {
        return CLASS_ALIAS_COLLISION;
    }
}

namespace {
    function main(): void
    {
        define('RuntimeConstantProvider\IMPORTED_VALUE', 'imported');
        define('RuntimeConstantConsumer\Config\PATH', 'qualified');
        define('CLASS_ALIAS_COLLISION', 'global-constant');
        define('RuntimeConstantProvider\CLASS_ALIAS_COLLISION', 'wrong-provider');
        define('IMPORTED', 'wrong-global');
        define('Config\PATH', 'wrong-qualified');

        var_dump(\RuntimeConstantConsumer\readImported());
        var_dump(\RuntimeConstantConsumer\readQualified());
        var_dump(\RuntimeConstantConsumer\readClassAliasCollision());
    }
}
?>
--EXPECT--
string(8) "imported"
string(9) "qualified"
string(15) "global-constant"
