--TEST--
Property defaults use Zend table values and restore runtime-only defaults
--FILE--
<?php

enum DefaultInitializationState
{
    case Ready;
}

class DefaultInitializationPaths
{
    public int $scalar = 1 + 2;
    public string $text = 'type' . 'php';
    public array $empty = [];
    public array $values = ['first'];
    public DefaultInitializationState $state = DefaultInitializationState::Ready;
}

class DefaultInitializationException extends Exception
{
    public array $context = ['runtime'];
}

function main(): void
{
    $first = new DefaultInitializationPaths();
    $second = new DefaultInitializationPaths();
    $first->values[] = 'second';

    var_dump($first->scalar, $first->text, $first->empty);
    var_dump($first->values, $second->values);
    var_dump($first->state === DefaultInitializationState::Ready);

    $exception = new DefaultInitializationException('message');
    var_dump($exception->getMessage(), $exception->context);
}
?>
--EXPECT--
int(3)
string(7) "typephp"
array(0) {
}
array(2) {
  [0]=>
  string(5) "first"
  [1]=>
  string(6) "second"
}
array(1) {
  [0]=>
  string(5) "first"
}
bool(true)
string(7) "message"
array(1) {
  [0]=>
  string(7) "runtime"
}
