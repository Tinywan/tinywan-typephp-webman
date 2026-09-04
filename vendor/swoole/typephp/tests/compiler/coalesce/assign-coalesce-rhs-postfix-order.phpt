--TEST--
??= completes the RHS postfix write-back before the target assignment
--FILE--
<?php
class State { public static mixed $assigned = null; }

class Source {
    private int $stored = 5;
    public int $value {
        get { return $this->stored; }
        set {
            var_dump(State::$assigned);
            $this->stored = $value;
        }
    }
}

function main(): void
{
    $source = new Source();
    State::$assigned ??= $source->value++;
    var_dump(State::$assigned);
    var_dump($source->value);
}
?>
--EXPECT--
NULL
int(5)
int(6)
