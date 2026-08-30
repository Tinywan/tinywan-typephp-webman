--TEST--
Class implements interface: parameter type compatibility
--FILE--
<?php

interface ContractA
{
    public function single(string $value);
    public function union2(string|int $value);
    public function union3(string|int|float $value);
    public function nullableUnion(string|int|null $value);
}

class ImplOmitAll implements ContractA
{
    public function single($value) { var_dump($value); }
    public function union2($value) { var_dump($value); }
    public function union3($value) { var_dump($value); }
    public function nullableUnion($value) { var_dump($value); }
}

interface ContractB
{
    public function mirror(string|int $x);
}

class ImplMirror implements ContractB
{
    public function mirror(string|int $x)
    {
        var_dump($x);
    }
}

interface ContractC
{
    public function widen(string $v);
}

class ImplWiden implements ContractC
{
    public function widen(string|int $v)
    {
        var_dump($v);
    }
}

interface ContractD
{
    public function a(string|bool $v);
}

interface ContractE
{
    public function b(int|float $v);
}

class ImplMulti implements ContractD, ContractE
{
    public function a($v) { var_dump($v); }
    public function b($v) { var_dump($v); }
}

function main()
{
    $a = new ImplOmitAll;
    $a->single('hello');
    $a->union2('world');
    $a->union2(42);
    $a->union3(1);
    $a->union3('two');
    $a->union3(3.14);
    $a->nullableUnion(100);
    $a->nullableUnion(null);

    $b = new ImplMirror;
    $b->mirror('ok');
    $b->mirror(99);

    $c = new ImplWiden;
    $c->widen('wide');

    $d = new ImplMulti;
    $d->a('yes');
    $d->a(true);
    $d->b(123);
    $d->b(4.56);
}
?>
--EXPECT--
string(5) "hello"
string(5) "world"
int(42)
int(1)
string(3) "two"
float(3.14)
int(100)
NULL
string(2) "ok"
int(99)
string(4) "wide"
string(3) "yes"
bool(true)
int(123)
float(4.56)
