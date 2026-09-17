--TEST--
StdVector parameter contracts in traits resolve self in the consuming class
--FILE--
<?php
trait VectorParameterConsumer
{
    public function accept(#[StdVector(self::class)] $vec): void
    {
        echo $vec[0]->id, "\n";
    }
}
class VectorParameterUser
{
    use VectorParameterConsumer;
    public function __construct(public int $id) {}
}
function main(): void
{
    $vec = std::vector(VectorParameterUser::class);
    $vec[] = new VectorParameterUser(9);
    $receiver = new VectorParameterUser(1);
    $receiver->accept($vec);
}
?>
--EXPECT--
9
