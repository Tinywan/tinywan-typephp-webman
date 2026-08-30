--TEST--
global constants, property defaults and parameter defaults with heredoc/nowdoc syntax
--FILE--
<?php

const G_HEREDOC = <<<ABC
    abc
    ABC;
const G_NOWDOC = <<<'DEF'
    def
    DEF;

class WithProp
{
    public string $p = <<<ABC
    xyz
    ABC;
}

function with_default(string $x = <<<'ABC'
$value ?? "quoted" \n \path
ABC): string {
    return $x;
}

function with_binary_default(string $x = <<<ABC
A\0B
ABC): string {
    return $x;
}

function main()
{
    var_dump(G_HEREDOC, G_NOWDOC);
    $o = new WithProp();
    var_dump($o->p);
    var_dump(bin2hex(with_default()), bin2hex(with_binary_default()));

    $default = (new ReflectionFunction('with_default'))->getParameters()[0]->getDefaultValue();
    $binaryDefault = (new ReflectionFunction('with_binary_default'))->getParameters()[0]->getDefaultValue();
    var_dump(bin2hex($default), bin2hex($binaryDefault));
}
?>
--EXPECT--
string(3) "abc"
string(3) "def"
string(3) "xyz"
string(54) "2476616c7565203f3f202271756f74656422205c6e205c70617468"
string(6) "410042"
string(54) "2476616c7565203f3f202271756f74656422205c6e205c70617468"
string(6) "410042"
