--TEST--
Runtime property defaults bypass hooks and asymmetric write handlers
--FILE--
<?php

class HookRuntimeDefault
{
    private int $writes = 0;

    public array $values = ['initial'] {
        get => $this->values;
        set {
            ++$this->writes;
            $this->values = $value;
        }
    }

    public function writes(): int
    {
        return $this->writes;
    }
}

class AsymmetricRuntimeDefault
{
    public private(set) array $values = ['initial'];
}

function main(): void
{
    $hooked = new HookRuntimeDefault();
    $asymmetric = new AsymmetricRuntimeDefault();

    var_dump($hooked->values, $hooked->writes());
    var_dump($asymmetric->values);
}
?>
--EXPECT--
array(1) {
  [0]=>
  string(7) "initial"
}
int(0)
array(1) {
  [0]=>
  string(7) "initial"
}
