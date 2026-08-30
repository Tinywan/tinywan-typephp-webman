--TEST--
class constants with heredoc and nowdoc syntax
--FILE--
<?php

class Test
{
    const VALUE1 = <<<ABC
    quote " slash \\ nul \0 tab \t ??
    ABC;
    const VALUE2 = <<<'DEF'
    $value ?? "quoted" \n \path
    DEF;
}

function main()
{
    var_dump(bin2hex(Test::VALUE1), bin2hex(Test::VALUE2));
}
?>
--EXPECT--
string(60) "71756f7465202220736c617368205c206e756c2000207461622009203f3f"
string(54) "2476616c7565203f3f202271756f74656422205c6e205c70617468"
