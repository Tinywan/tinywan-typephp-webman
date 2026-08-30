--TEST--
ternary expressions convert void branches to null after their side effects
--FILE--
<?php

class OptimisticLock
{
    private int $calls = 0;

    public function check(bool $exists)
    {
        return $exists
            ? $this->updateLockVersion()
            : $this->recordLockVersion();
    }

    public function calls(): int
    {
        return $this->calls;
    }

    private function updateLockVersion(): void
    {
        echo "update\n";
        $this->calls++;
    }

    private function recordLockVersion(): void
    {
        echo "record\n";
        $this->calls++;
    }
}

function main(): void
{
    $lock = new OptimisticLock();
    var_dump($lock->check(true));
    var_dump($lock->check(false));
    var_dump($lock->calls());
}
?>
--EXPECT--
update
NULL
record
NULL
int(2)
