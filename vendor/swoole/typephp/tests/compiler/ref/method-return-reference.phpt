--TEST--
methods returning by reference preserve aliases
--FILE--
<?php
class RefBox
{
    public $value = 1;
    public static $staticValue = 10;

    public function &valueRef()
    {
        return $this->value;
    }

    public static function &staticRef()
    {
        return self::$staticValue;
    }

    public function valueCopy()
    {
        return $this->value;
    }

    public static function staticCopy()
    {
        return self::$staticValue;
    }
}

function main()
{
    $box = new RefBox();

    $methodAlias =& $box->valueRef();
    $methodAlias = 42;
    var_dump($box->valueRef());

    $staticAlias =& RefBox::staticRef();
    $staticAlias = 77;
    var_dump(RefBox::staticRef());

    $GLOBALS['eval_box'] = $box;
    eval('$evalMethodAlias =& $GLOBALS["eval_box"]->valueRef(); $evalMethodAlias = "method eval"; $evalStaticAlias =& RefBox::staticRef(); $evalStaticAlias = "static eval";');
    var_dump($box->valueRef());
    var_dump(RefBox::staticRef());

    require __DIR__ . '/method-return-reference-require.inc';
    var_dump($box->valueRef());
    var_dump(RefBox::staticRef());

    $method = 'valueRef';
    $dynamicMethodAlias =& $box->$method();
    $dynamicMethodAlias = 'method dynamic';
    var_dump($box->valueRef());

    $staticMethod = 'staticRef';
    $dynamicStaticAlias =& RefBox::$staticMethod();
    $dynamicStaticAlias = 'static dynamic';
    var_dump(RefBox::staticRef());

    $method = 'valueCopy';
    try {
        $badMethodAlias =& $box->$method();
    } catch (TypeError $e) {
        echo "dynamic method TypeError\n";
    }

    $staticMethod = 'staticCopy';
    try {
        $badStaticAlias =& RefBox::$staticMethod();
    } catch (TypeError $e) {
        echo "dynamic static TypeError\n";
    }
}
?>
--EXPECT--
int(42)
int(77)
string(11) "method eval"
string(11) "static eval"
string(14) "method require"
string(14) "static require"
string(14) "method dynamic"
string(14) "static dynamic"
dynamic method TypeError
dynamic static TypeError
