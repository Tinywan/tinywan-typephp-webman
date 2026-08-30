--TEST--
Native class: ternary, match and coalesce preserve native pointer types
--FILE--
<?php

#[Native]
class NativeSelectedValue
{
    public int $value;
    public ?NativeSelectedValue $child;

    public function __construct(int $value)
    {
        $this->value = $value;
    }
}

#[Native]
class NativeSelectedBase
{
    public int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }
}

#[Native]
class NativeSelectedLeft extends NativeSelectedBase
{
}

#[Native]
class NativeSelectedRight extends NativeSelectedBase
{
}

function selectSibling(bool $left): NativeSelectedBase
{
    return $left ? new NativeSelectedLeft(90) : new NativeSelectedRight(91);
}

function matchSibling(bool $left): NativeSelectedBase
{
    return match ($left) {
        true => new NativeSelectedLeft(92),
        false => new NativeSelectedRight(93),
    };
}

function coalesceSibling(?NativeSelectedLeft $left): NativeSelectedBase
{
    return $left ?? new NativeSelectedRight(94);
}

function selectWithMatch(int $kind): NativeSelectedValue
{
    return match ($kind) {
        1 => new NativeSelectedValue(10),
        default => new NativeSelectedValue(20),
    };
}

function selectWithCoalesce(?NativeSelectedValue $value): NativeSelectedValue
{
    return $value ?? new NativeSelectedValue(30);
}

function makeSelectedValue(): NativeSelectedValue
{
    echo "made\n";
    return new NativeSelectedValue(70);
}

function identitySelectedValue(NativeSelectedValue $value): NativeSelectedValue
{
    return $value;
}

function main(): void
{
    $first = true ? new NativeSelectedValue(1) : new NativeSelectedValue(2);
    var_dump($first->value);
    var_dump(selectWithMatch(1)->value, selectWithMatch(2)->value);
    var_dump(selectWithCoalesce(null)->value);
    var_dump(selectWithCoalesce($first)->value);

    $created ??= new NativeSelectedValue(40);
    $created ??= new NativeSelectedValue(41);
    var_dump($created->value);

    $holder = new NativeSelectedValue(50);
    $holder->child ??= new NativeSelectedValue(60);
    var_dump($holder->child->value);

    $existing = new NativeSelectedValue(80);
    $existing ??= identitySelectedValue(makeSelectedValue());
    var_dump($existing->value);

    var_dump(selectSibling(true)->value, selectSibling(false)->value);
    var_dump(matchSibling(true)->value, matchSibling(false)->value);
    var_dump(coalesceSibling(null)->value);
    var_dump(coalesceSibling(new NativeSelectedLeft(95))->value);
}
?>
--EXPECT--
int(1)
int(10)
int(20)
int(30)
int(1)
int(40)
int(60)
int(80)
int(90)
int(91)
int(92)
int(93)
int(94)
int(95)
