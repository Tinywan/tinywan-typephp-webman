<?php

// Test static property with different data types (if supported)
class DataTypeTest
{
    static int $int_prop = 42;
    static float $float_prop = 3.14;
    static bool $bool_prop = true;
}


// Test static properties in different context
class AnotherClass
{
    static string $counter = '0';

    public static function increment() {
        self::$counter = strval(intval(self::$counter) + 1);
        return self::$counter;
    }
}

class TestClass
{
    public static string $public_static_property = 'initial_value';
    protected static string $protected_static_property = 'protected_value';
    private static string $private_static_property = 'private_value';
    static string $default_static_property = 'default_value';

    public static function getProtectedProperty()
    {
        return self::$protected_static_property;
    }

    public static function getPrivateProperty()
    {
        return self::$private_static_property;
    }

    public static function setProtectedProperty(string $value) {
        self::$protected_static_property = $value;
    }

    public static function setPrivateProperty(string $value) {
        self::$private_static_property = $value;
    }
}