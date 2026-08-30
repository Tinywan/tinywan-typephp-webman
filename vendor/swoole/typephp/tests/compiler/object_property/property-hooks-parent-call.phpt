--TEST--
Property hooks may call the corresponding parent get and set hook
--FILE--
<?php

class ParentPropertyHookCall
{
    protected string $stored = '';

    public string $value {
        get => 'parent-get:' . $this->stored;
        set {
            $this->stored = 'parent-set:' . $value;
        }
    }
}

class ChildPropertyHookCall extends ParentPropertyHookCall
{
    public string $value {
        get => parent::$value::get() . ':child-get';
        set {
            parent::$value::set($value . ':child-set');
        }
    }
}

function main(): void
{
    $point = new ChildPropertyHookCall();
    $point->value = 'data';
    var_dump($point->value);
}
?>
--EXPECT--
string(46) "parent-get:parent-set:data:child-set:child-get"
