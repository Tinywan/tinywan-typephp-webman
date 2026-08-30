--TEST--
Symfony pattern: array dim nullsafe call with boolean short-circuit
--FILE--
<?php

final class DeferredFuture
{
    public bool $completed = false;

    public function isComplete(): bool
    {
        echo "isComplete\n";
        return $this->completed;
    }

    public function complete(): void
    {
        echo "complete\n";
        $this->completed = true;
    }
}

final class Multi
{
    public array $openHandles = [];
}

function cancelHandle(Multi $multi, string $id): void
{
    $multi->openHandles[$id]?->isComplete() || $multi->openHandles[$id]?->complete();
}

function main(): void
{
    $multi = new Multi();
    $multi->openHandles['a'] = new DeferredFuture();

    cancelHandle($multi, 'a');
    var_dump($multi->openHandles['a']->completed);

    cancelHandle($multi, 'a');

    $multi->openHandles['b'] = null;
    cancelHandle($multi, 'b');
    echo "done\n";
}
?>
--EXPECT--
isComplete
complete
bool(true)
isComplete
done
