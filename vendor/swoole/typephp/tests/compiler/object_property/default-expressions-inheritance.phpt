--TEST--
Property defaults support constant expressions, inheritance, and traits
--FILE--
<?php

const GLOBAL_NUMBER = 4;

trait PropertyDefaultsTrait
{
    public int $traitValue = 10 + 1;
    public static string $traitStatic = 'trait' . '-static';
}

class PropertyDefaultsParent
{
    private const LABEL_PREFIX = 'parent';

    public int $sum = 1 + 2;
    public float $ratio = 2;
    protected string $label = self::LABEL_PREFIX . '-value';
    protected array $options = ['mode' => 'parent'];
    public static int $counter = GLOBAL_NUMBER + 1;
    public static float $staticRatio = 3;

    public function label(): string
    {
        return $this->label;
    }

    public function options(): array
    {
        return $this->options;
    }
}

class PropertyDefaultsChild extends PropertyDefaultsParent
{
    use PropertyDefaultsTrait;
}

function main(): void
{
    $value = new PropertyDefaultsChild();
    var_dump($value->sum, $value->ratio, $value->label(), $value->options(), $value->traitValue);
    var_dump(
        PropertyDefaultsChild::$counter,
        PropertyDefaultsChild::$staticRatio,
        PropertyDefaultsChild::$traitStatic
    );
}
?>
--EXPECT--
int(3)
float(2)
string(12) "parent-value"
array(1) {
  ["mode"]=>
  string(6) "parent"
}
int(11)
int(5)
float(3)
string(12) "trait-static"
