--TEST--
Assignment expressions used as method receivers preserve PHP evaluation semantics
--FILE--
<?php

class AssignmentReceiver
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function id(): string
    {
        return $this->value;
    }

    public function append(string $suffix): string
    {
        return $this->value . $suffix;
    }
}

class AssignmentReceiverFactory extends AssignmentReceiver
{
    public static function fromSelf(): string
    {
        return ($receiver = new self('self'))->id();
    }

    public static function fromStatic(): string
    {
        return ($receiver = new static('static'))->id();
    }
}

class AssignmentReceiverChild extends AssignmentReceiverFactory
{
}

class AssignmentReceiverHolder
{
    public AssignmentReceiver $receiver;

    public function __construct(AssignmentReceiver $receiver)
    {
        $this->receiver = $receiver;
    }
}

function makeAssignmentReceiver(int &$calls, string $value): AssignmentReceiver
{
    ++$calls;
    return new AssignmentReceiver($value);
}

function main(): void
{
    $original = new AssignmentReceiver('base');

    echo ($assigned = $original)->id(), "\n";
    var_dump($assigned === $original);

    // A call with arguments already materializes its receiver; retain it as a
    // control for the no-argument path fixed by this regression.
    echo ($withArgument = $original)->append('-arg'), "\n";

    $calls = 0;
    echo ($created = makeAssignmentReceiver($calls, 'factory'))->id(), "\n";
    var_dump($calls);
    echo $created->id(), "\n";

    echo AssignmentReceiverFactory::fromSelf(), "\n";
    echo AssignmentReceiverChild::fromStatic(), "\n";

    $holder = new AssignmentReceiverHolder($original);
    echo ($holder->receiver = new AssignmentReceiver('property'))->id(), "\n";

    $items = [];
    echo ($items[0] = new AssignmentReceiver('array'))->id(), "\n";

    echo ($left = $right = new AssignmentReceiver('chain'))->id(), "\n";
    var_dump($left === $right);

    echo ($reference =& $original)->id(), "\n";
    var_dump($reference === $original);
}
?>
--EXPECT--
base
bool(true)
base-arg
factory
int(1)
factory
self
static
property
array
chain
bool(true)
base
bool(true)
