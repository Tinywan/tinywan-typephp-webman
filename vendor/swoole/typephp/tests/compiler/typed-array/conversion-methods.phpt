--TEST--
toStdList and toStdDict validate converted PHP arrays and preserve typed assignment
--FILE--
<?php
class ConvertedValue {}
class ConvertedChild extends ConvertedValue {}

class ConvertedSource {
    public function toArray(): array { return ['answer' => 42]; }
}

#[Native]
class ConvertedNativeSource {
    public function toArray(): array { return [12]; }
}

function make_source(): array {
    echo "source called\n";
    return [13];
}

function receive_list(#[StdList(Type::Int)] array $values): int {
    return $values[0];
}

function main(): void {
    $rawList = [4, 5];
    $list = $rawList->toStdList(Type::Int);
    $copy = $list->toStdList(Type::Int);
    $copy[] = 6;
    var_dump($list, $copy);

    $rawDict = ['answer' => 42];
    $dict = $rawDict->toStdDict(Type::Str, Type::Int);
    var_dump($dict['answer']);

    $number = 9;
    $fromScalar = $number->toStdList(Type::Int);
    var_dump($fromScalar);

    $object = new ConvertedSource();
    $fromObject = $object->toStdDict(Type::Str, Type::Int);
    var_dump($fromObject);

    $dynamic = std::any([7, 8]);
    $fromDynamic = $dynamic->toStdList(Type::Int);
    var_dump($fromDynamic[1], receive_list($rawList->toStdList(Type::Int)));

    $integerKeys = [10 => 'ten'];
    $integerDict = $integerKeys->toStdDict(Type::Int, Type::Str);
    var_dump($integerDict[10]);

    $vector = std::vector(Type::Int);
    $vector[] = 11;
    $fromVector = $vector->toStdList(Type::Int);
    var_dump($fromVector[0]);

    $native = new ConvertedNativeSource();
    $fromNative = $native->toStdList(Type::Int);
    var_dump($fromNative[0]);
    $fromCall = make_source()->toStdList(Type::Int);
    var_dump($fromCall[0]);

    $rawObjects = [new ConvertedValue(), new ConvertedChild()];
    $objects = $rawObjects->toStdList(ConvertedValue::class);
    var_dump(count($objects), $objects[1] instanceof ConvertedChild);

    try {
        $badListKeys = ['name' => 1];
        $badListKeys->toStdList(Type::Int);
    } catch (TypeError $error) { echo "list key rejected\n"; }
    try {
        $badListValues = [1, 'two'];
        $badListValues->toStdList(Type::Int);
    } catch (TypeError $error) { echo "list value rejected\n"; }
    try {
        $badDictKeys = [1 => 1];
        $badDictKeys->toStdDict(Type::Str, Type::Int);
    } catch (TypeError $error) { echo "dict key rejected\n"; }
    try {
        $normalizedNumericKey = ['123' => 1];
        $normalizedNumericKey->toStdDict(Type::Str, Type::Int);
    } catch (TypeError $error) { echo "normalized key rejected\n"; }
    try {
        $badDictValues = ['one' => 'wrong'];
        $badDictValues->toStdDict(Type::Str, Type::Int);
    } catch (TypeError $error) { echo "dict value rejected\n"; }
    try {
        $badObjects = [new stdClass()];
        $badObjects->toStdList(ConvertedValue::class);
    } catch (TypeError $error) { echo "class rejected\n"; }
    try {
        $otherType = std::list(Type::Str);
        $otherType[] = 'not an integer';
        $otherType->toStdList(Type::Int);
    } catch (TypeError $error) { echo "different contract rejected\n"; }
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(4)
  [1]=>
  int(5)
}
array(3) {
  [0]=>
  int(4)
  [1]=>
  int(5)
  [2]=>
  int(6)
}
int(42)
array(1) {
  [0]=>
  int(9)
}
array(1) {
  ["answer"]=>
  int(42)
}
int(8)
int(4)
string(3) "ten"
int(11)
int(12)
source called
int(13)
int(2)
bool(true)
list key rejected
list value rejected
dict key rejected
normalized key rejected
dict value rejected
class rejected
different contract rejected
