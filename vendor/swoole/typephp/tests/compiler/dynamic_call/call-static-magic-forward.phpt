--TEST--
__callStatic forwards to static method through call_user_func_array
--FILE--
<?php
class StaticForwarder
{
    public static function __callStatic($name, $arguments): bool
    {
        if ('all' === substr($name, 0, 3)) {
            $method = lcfirst(substr($name, 3));
            $args = $arguments;
            foreach ($arguments[0] as $entry) {
                $args[0] = $entry;
                if (!call_user_func_array(['static', $method], $args)) {
                    return false;
                }
            }
            return true;
        }

        if ('nullOr' === substr($name, 0, 6)) {
            if ($arguments[0] !== null) {
                $method = lcfirst(substr($name, 6));
                return call_user_func_array(['static', $method], $arguments);
            }
            return true;
        }

        return false;
    }

    public static function lengthBetween($value, int $min, int $max): bool
    {
        $len = strlen($value);
        return $len >= $min && $len <= $max;
    }
}

function main()
{
    var_dump(StaticForwarder::__callStatic('allLengthBetween', [['aa', 'bbbb'], 2, 4]));
    var_dump(StaticForwarder::__callStatic('allLengthBetween', [['a', 'bbbb'], 2, 4]));
    var_dump(StaticForwarder::__callStatic('nullOrLengthBetween', [null, 2, 4]));
    var_dump(StaticForwarder::__callStatic('nullOrLengthBetween', ['abc', 2, 4]));
}
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(true)
