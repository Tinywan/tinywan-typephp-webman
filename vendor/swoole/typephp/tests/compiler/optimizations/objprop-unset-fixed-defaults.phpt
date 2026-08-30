--TEST--
SSA object prop: unset fixed typed properties restores declared defaults
--FILE--
<?php
use native_types;

class FixedDefaults {
    public int $i;
    public float $f;
    public bool $b;
    public string $s;
    public array $a;
    public int $di = 42;
    public string $ds = "seed";
    public array $da = [1, 2];

    public function run(): void {
        $this->i = 9;
        $this->f = 2.5;
        $this->b = true;
        $this->s = "changed";
        $this->a = ["x"];
        $this->di = 77;
        $this->ds = "changed";
        $this->da = [9];

        unset($this->i);
        unset($this->f);
        unset($this->b);
        unset($this->s);
        unset($this->a);
        unset($this->di);
        unset($this->ds);
        unset($this->da);

        var_dump(isset($this->i), $this->i);
        var_dump(isset($this->f), $this->f);
        var_dump(isset($this->b), $this->b);
        var_dump(isset($this->s), $this->s);
        var_dump(isset($this->a), $this->a);
        var_dump(isset($this->di), $this->di);
        var_dump(isset($this->ds), $this->ds);
        var_dump(isset($this->da), $this->da);
    }
}

function main(): void {
    (new FixedDefaults())->run();
}
?>
--EXPECT--
bool(true)
int(0)
bool(true)
float(0)
bool(true)
bool(false)
bool(true)
string(0) ""
bool(true)
array(0) {
}
bool(true)
int(42)
bool(true)
string(4) "seed"
bool(true)
array(2) {
  [0]=>
  int(1)
  [1]=>
  int(2)
}
