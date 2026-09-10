--TEST--
Closure reference captures automatically degrade inferred locals to var storage
--FILE--
<?php
function make_counter(): Closure
{
    $count = 0;
    return static function () use (&$count): int {
        return ++$count;
    };
}

function replace_parameter(string $value): Closure
{
    return static function () use (&$value): array {
        $value = ['parameter'];
        return $value;
    };
}

final class ParameterCapture
{
    public function replace(array $value): Closure
    {
        return function () use (&$value): string {
            $value = 'method';
            return $value;
        };
    }
}

function main(): void
{
    $counter = make_counter();
    var_dump($counter());
    var_dump($counter());

    $value = 'before';
    $change = static function () use (&$value): void {
        $value = ['after'];
    };
    $change();
    var_dump($value);

    $object = new stdClass();
    $replaceObject = static function () use (&$object): void {
        $object = 'replaced';
    };
    $replaceObject();
    var_dump($object);

    $replaceParameter = replace_parameter('before');
    var_dump($replaceParameter());
    var_dump((new ParameterCapture())->replace([])());
}
?>
--EXPECT--
int(1)
int(2)
array(1) {
  [0]=>
  string(5) "after"
}
string(8) "replaced"
array(1) {
  [0]=>
  string(9) "parameter"
}
string(6) "method"
