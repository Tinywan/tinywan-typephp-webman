--TEST--
class_alias with ::class constant
--FILE--
<?php
class AliasOriginal
{
    public static function name(): string
    {
        return static::class;
    }

    public static function ok(): string
    {
        return 'ok';
    }

    public function value(): string
    {
        return self::class;
    }
}

function main()
{
    class_alias(AliasOriginal::class, 'AliasCopy');

    $obj = new AliasCopy();
    var_dump($obj instanceof AliasOriginal);
    var_dump($obj instanceof AliasCopy);
    var_dump($obj->value());
    var_dump(AliasCopy::ok());
}
?>
--EXPECT--
bool(true)
bool(true)
string(13) "AliasOriginal"
string(2) "ok"
