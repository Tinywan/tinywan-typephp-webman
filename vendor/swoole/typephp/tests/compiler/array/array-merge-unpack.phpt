--TEST--
array merge unpack
--FILE--
<?php
class Dg {
    private int $c;
    private array $r;

    public function __construct(int $c = 3) {
        $this->c = $c;
        $this->r = [];
    }

    public function g(): self {
        for ($i = 0; $i < $this->c; $i++) {
            $this->r[] = [
                'bid' => time(),
                'ct'  => 1,
                'cid' => random_int(100000000, 2147483647),
                'cp'  => strtoupper(substr(bin2hex(random_bytes(8)), 0, 12)),
                'crt' => date('Y-m-d H:i:s'),
                'mem' => '',
            ];
        }
        return $this;
    }
    //array_merge
    public function f1(): array {
        return array_merge(...array_map('array_values', $this->r));
    }
    //双重 foreach
    public function f2(): array {
        $res = [];
        foreach ($this->r as $row) {
            foreach ($row as $v) {
                $res[] = $v;
            }
        }
        return $res;
    }

    public function chk(): void {
        $exp = $this->c * count($this->r[0]);
        $spr = count($this->f1());
        $loo = count($this->f2());

        echo "Rows: {$this->c}\n";
        echo "Cols: " . count($this->r[0]) . "\n";
        echo "Exp : {$exp}\n";
        echo "Spr : {$spr}\n";
        echo "Loo : {$loo}\n\n";
        echo ($spr === $exp && $loo === $exp) ? "PASS" : "FAIL";
        echo "\n\n";
    }
}

function main(): void
{
    (new Dg(3))->g()->chk();
}
?>
--EXPECT--
Rows: 3
Cols: 6
Exp : 18
Spr : 18
Loo : 18

PASS
