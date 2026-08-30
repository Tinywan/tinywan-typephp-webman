--TEST--
inherited static property accessed through static:: remains isolated per class
--FILE--
<?php
class StaticOptionBase
{
    protected static $enabled = true;
    protected static $events = [];

    public static function setEnabled(bool $enabled): void
    {
        static::$enabled = $enabled;
        static::$events[] = static::class . ':' . ($enabled ? 'on' : 'off');
    }

    public static function state(): array
    {
        return [static::class, static::$enabled, static::$events];
    }
}

class StaticOptionChildA extends StaticOptionBase
{
    protected static $enabled = false;
    protected static $events = [];
}

class StaticOptionChildB extends StaticOptionBase
{
    protected static $enabled = true;
    protected static $events = [];
}

function main()
{
    StaticOptionChildA::setEnabled(true);
    StaticOptionChildB::setEnabled(false);
    StaticOptionChildA::setEnabled(false);

    var_dump(StaticOptionBase::state());
    var_dump(StaticOptionChildA::state());
    var_dump(StaticOptionChildB::state());
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(16) "StaticOptionBase"
  [1]=>
  bool(true)
  [2]=>
  array(0) {
  }
}
array(3) {
  [0]=>
  string(18) "StaticOptionChildA"
  [1]=>
  bool(false)
  [2]=>
  array(2) {
    [0]=>
    string(21) "StaticOptionChildA:on"
    [1]=>
    string(22) "StaticOptionChildA:off"
  }
}
array(3) {
  [0]=>
  string(18) "StaticOptionChildB"
  [1]=>
  bool(false)
  [2]=>
  array(1) {
    [0]=>
    string(22) "StaticOptionChildB:off"
  }
}
