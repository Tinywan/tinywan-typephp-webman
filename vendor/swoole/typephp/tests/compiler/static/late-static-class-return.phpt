--TEST--
static::class resolves to the called class through string-typed methods
--FILE--
<?php
declare(strict_types=1);

trait LsbClassTrait
{
    public function traitClass(): string
    {
        return static::class;
    }
}

class LsbBase
{
    use LsbClassTrait;

    public static function staticClass(): string
    {
        return static::class;
    }

    public function instanceClass(): string
    {
        return static::class;
    }

    public static function viaVar(): string
    {
        $name = static::class;
        return $name;
    }

    public static function untypedClass()
    {
        return static::class;
    }
}

class LsbChild extends LsbBase
{
}

final class LsbGrandChild extends LsbChild
{
}

function main(): void
{
    // Called class is the declaring class.
    var_dump(LsbBase::staticClass());
    var_dump((new LsbBase())->instanceClass());

    // Late static binding through a static call.
    var_dump(LsbChild::staticClass());
    var_dump(LsbGrandChild::staticClass());

    // Late static binding through an inherited instance method.
    var_dump((new LsbChild())->instanceClass());
    var_dump((new LsbGrandChild())->instanceClass());

    // The result assigned to a local variable and returned.
    var_dump(LsbChild::viaVar());

    // static::class in a trait body resolves to the consuming class.
    var_dump((new LsbChild())->traitClass());

    // Untyped return keeps the runtime value intact.
    var_dump(LsbGrandChild::untypedClass());
}
?>
--EXPECT--
string(7) "LsbBase"
string(7) "LsbBase"
string(8) "LsbChild"
string(13) "LsbGrandChild"
string(8) "LsbChild"
string(13) "LsbGrandChild"
string(8) "LsbChild"
string(8) "LsbChild"
string(13) "LsbGrandChild"
