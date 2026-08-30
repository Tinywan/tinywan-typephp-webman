--TEST--
Nullsafe receiver temporaries release weak targets after the statement
--FILE--
<?php

final class NullsafeWeakReceiver
{
    public string $value = 'probe';

    public function label(): string
    {
        return $this->value;
    }
}

function methodReceiver(): array
{
    $target = new NullsafeWeakReceiver();
    $weak = WeakReference::create($target);
    $observed = $weak->get()?->label();
    unset($target);
    gc_collect_cycles();
    return [$observed, $weak->get() === null];
}

function propertyReceiver(): array
{
    $target = new NullsafeWeakReceiver();
    $weak = WeakReference::create($target);
    $observed = $weak->get()?->value;
    unset($target);
    gc_collect_cycles();
    return [$observed, $weak->get() === null];
}

function main(): void
{
    var_dump(methodReceiver());
    var_dump(propertyReceiver());
}

?>
--EXPECT--
array(2) {
  [0]=>
  string(5) "probe"
  [1]=>
  bool(true)
}
array(2) {
  [0]=>
  string(5) "probe"
  [1]=>
  bool(true)
}
