--TEST--
parent dynamic method lookup uses the lexical parent class and current call scope
--FILE--
<?php

class ParentDynamicMethod
{
    public function greet(string $name): string
    {
        return 'parent:' . $name;
    }

    protected function protectedGreet(): string
    {
        return 'protected parent';
    }
}

class ChildDynamicMethod extends ParentDynamicMethod
{
    public function greet(string $name): string
    {
        return 'child:' . $name;
    }

    public function callParent(string $method, mixed ...$args): mixed
    {
        return parent::$method(...$args);
    }
}

class GrandchildDynamicMethod extends ChildDynamicMethod
{
    public function greet(string $name): string
    {
        return 'grandchild:' . $name;
    }
}

class ParentDynamicStaticMethod
{
    public static function greet(string $name): string
    {
        return 'static parent:' . $name;
    }
}

class ChildDynamicStaticMethod extends ParentDynamicStaticMethod
{
    public static function greet(string $name): string
    {
        return 'static child:' . $name;
    }

    public static function callParent(string $method, mixed ...$args): mixed
    {
        return parent::$method(...$args);
    }
}

function main(): void
{
    $child = new ChildDynamicMethod;
    var_dump($child->callParent('greet', 'Ada'));
    var_dump($child->callParent('protectedGreet'));

    $grandchild = new GrandchildDynamicMethod;
    var_dump($grandchild->callParent('greet', 'Lin'));

    var_dump(ChildDynamicStaticMethod::callParent('greet', 'Sam'));
}
?>
--EXPECT--
string(10) "parent:Ada"
string(16) "protected parent"
string(10) "parent:Lin"
string(17) "static parent:Sam"
