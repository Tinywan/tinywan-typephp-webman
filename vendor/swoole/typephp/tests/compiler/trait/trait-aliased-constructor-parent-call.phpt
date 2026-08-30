--TEST--
Trait parent constructor calls keep declarations aligned for aliased and direct wrappers
--FILE--
<?php

class Base
{
    public function __construct(array $options = [])
    {
        var_dump($options);
    }
}

trait DriverConstructor
{
    public function __construct(array $options = [])
    {
        $options['trait'] = 1;
        parent::__construct($options);
    }
}

class AliasedDriver extends Base
{
    use DriverConstructor {
        __construct as private traitConstruct;
    }

    public function __construct(array $options = [])
    {
        $options['alias'] = 1;
        $this->traitConstruct($options);
    }
}

class DirectDriver extends Base
{
    use DriverConstructor;
}

function main()
{
    new AliasedDriver(['input' => 1]);
    new DirectDriver(['direct' => 1]);
}
?>
--EXPECT--
array(3) {
  ["input"]=>
  int(1)
  ["alias"]=>
  int(1)
  ["trait"]=>
  int(1)
}
array(2) {
  ["direct"]=>
  int(1)
  ["trait"]=>
  int(1)
}
