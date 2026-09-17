--TEST--
Std container parameter declarations restore checked shared containers without toStd calls
--FILE--
<?php
use StdVector as VectorOf;

function append_values(#[VectorOf(Type::Int)] $vec): void
{
    $vec[] = 42;
    foreach ($vec as $value) { echo $value, "\n"; }
}

function fill_map(#[StdMap(Type::String, Type::Float)] $map): void
{
    $map['value'] = 2.5;
}

function box_value($value) { return $value; }

class ParameterUser
{
    public function __construct(public int $id) {}
}

class ParameterReceiver
{
    public function accept(#[StdOrderedMap(Type::Int, ParameterUser::class)] $users): void
    {
        echo $users[0]->id, "\n";
    }
}

function main(): void
{
    $vec = std::vector(Type::Int);
    $vec[] = 7;
    append_values(vec: $vec);
    var_dump($vec[1]);
    $callback = 'append_values';
    $box = box_value($vec);
    $callback($box);
    var_dump(count($vec));

    $map = std::map(Type::String, Type::Float);
    fill_map($map);
    var_dump($map['value']);

    $users = std::orderedMap(Type::Int, ParameterUser::class);
    $users[0] = new ParameterUser(9);
    $receiver = new ParameterReceiver();
    $receiver->accept($users);

    $wrong = std::vector(Type::Float);
    try { append_values($wrong); } catch (TypeError $e) { echo "wrong value type\n"; }
    try { append_values($map); } catch (TypeError $e) { echo "wrong container kind\n"; }
    try { append_values([1, 2]); } catch (TypeError $e) { echo "not a container\n"; }
}
?>
--EXPECT--
7
42
int(42)
7
42
42
int(3)
float(2.5)
9
wrong value type
wrong container kind
not a container
