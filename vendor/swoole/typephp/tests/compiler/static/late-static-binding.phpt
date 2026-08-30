--TEST--
Early Static Binding - late static binding with static:: and self::
--FILE--
<?php
class ParentClass {
    public static function who(): string {
        return __CLASS__;
    }
    
    public static function test_self(): string {
        return self::who();
    }
    
    public static function test_static(): string {
        return static::who();
    }
}

class Child extends ParentClass {
    public static function who(): string {
        return __CLASS__;
    }
}

// Test with static properties
class Config {
    public static array $settings = [];
    
    public static function get(string $key): mixed {
        return static::$settings[$key] ?? null;
    }
    
    public static function set(string $key, mixed $value): void {
        static::$settings[$key] = $value;
    }
}

// 在 main 中初始化不同的配置
function test_static_properties() {
    // DevConfig 使用自己的 settings
    \DevConfig::$settings = ['dev' => true, 'debug' => true];
    var_dump(DevConfig::get('dev'));
    var_dump(DevConfig::get('debug'));
    
    // ProdConfig 使用自己的 settings
    \ProdConfig::$settings = ['prod' => true, 'debug' => false];
    var_dump(ProdConfig::get('prod'));
    var_dump(ProdConfig::get('debug'));
}

class DevConfig extends Config {
    // 子类使用自己的属性
}

class ProdConfig extends Config {
    // 子类使用自己的属性
}

// Test with static method override
class Factory {
    protected static string $className = 'BaseClass';
    
    public static function create(): object {
        return new static::$className();
    }
    
    public static function getClassName(): string {
        return static::$className;
    }
}

class UserFactory extends Factory {
    protected static string $className = 'User';
}

class ProductFactory extends Factory {
    protected static string $className = 'Product';
}

class User {}
class Product {}

// Test in constructor
class Base {
    public function __construct() {
        echo "Base constructor\n";
        static::init();
    }
    
    public static function init(): void {
        echo "Base init\n";
    }
}

class Derived extends Base {
    public static function init(): void {
        echo "Derived init\n";
    }
}

function main() {
    // Test basic late static binding
    var_dump(ParentClass::test_self());    // Should print "ParentClass"
    var_dump(ParentClass::test_static());  // Should print "ParentClass"
    var_dump(Child::test_self());     // Should print "ParentClass" (self refers to Parent)
    var_dump(Child::test_static());   // Should print "Child" (static refers to called class)

    // Test with static properties
    test_static_properties();

    // Test with static method override
    var_dump(UserFactory::getClassName());
    var_dump(ProductFactory::getClassName());

    // Test in constructor
    new Derived();
}
?>
--EXPECT--
string(11) "ParentClass"
string(11) "ParentClass"
string(11) "ParentClass"
string(5) "Child"
bool(true)
bool(true)
bool(true)
bool(false)
string(4) "User"
string(7) "Product"
Base constructor
Derived init
