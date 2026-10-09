--TEST--
Native class: fixed-width scalar fields retain PHP scalar expression types
--FILE--
<?php

#[Native]
final class CompactScalars
{
    public int8 $i8 = -8;
    public int16 $i16 = -1600;
    public int32 $i32 = -320000;
    public uint8 $u8 = 8;
    public uint16 $u16 = 1600;
    public uint32 $u32 = 4294967295;
    public float32 $f32 = 16777216.0;
    public float32 $f32unit = 1.0;
    public int32 $negative = -1;
    public int32 $one = 1;

    public function update(): void
    {
        $this->i8 += 2;
        $this->u16 *= 2;
    }

    public function intSum(): int
    {
        return $this->u32 + $this->one;
    }

    public function intCompare(): bool
    {
        return $this->u32 > $this->negative;
    }

    public function floatSum(): float
    {
        return $this->f32 + $this->f32unit;
    }
}

#[Native]
final class MixedScalars
{
    public int $integer = 3;
    public float $floating = 0.5;
    public int8 $smallInteger = 2;
    public float32 $smallFloat = 0.25;

    public function nativeSum(): float
    {
        return $this->integer + $this->floating;
    }

    public function fixedIntSum(): float
    {
        return $this->smallInteger + $this->floating;
    }

    public function fixedFloatSum(): float
    {
        return $this->integer + $this->smallFloat;
    }
}

function main(): void
{
    $value = new CompactScalars();
    $value->update();

    var_dump(
        $value->i8,
        $value->i16,
        $value->i32,
        $value->u8,
        $value->u16,
        $value->u32,
        $value->intSum(),
        $value->intCompare(),
        $value->floatSum(),
    );

    $mixed = new MixedScalars();
    var_dump($mixed->nativeSum(), $mixed->fixedIntSum(), $mixed->fixedFloatSum());
}
?>
--EXPECT--
int(-6)
int(-1600)
int(-320000)
int(8)
int(3200)
int(4294967295)
int(4294967296)
bool(true)
float(16777217)
float(3.5)
float(2.5)
float(3.25)
