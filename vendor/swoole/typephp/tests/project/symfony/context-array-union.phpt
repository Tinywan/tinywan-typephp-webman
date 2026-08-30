--TEST--
Symfony pattern: context array union keeps left-hand keys
--FILE--
<?php

class SymfonyLikeSender
{
}

function logContext(object $message, string $alias, object $sender): array
{
    $context = [
        'class' => $message::class,
        'alias' => 'existing',
    ];

    return $context + ['alias' => $alias, 'sender' => $sender::class];
}

function main(): void
{
    var_dump(logContext(new stdClass(), 'async', new SymfonyLikeSender()));
}
?>
--EXPECT--
array(3) {
  ["class"]=>
  string(8) "stdClass"
  ["alias"]=>
  string(8) "existing"
  ["sender"]=>
  string(17) "SymfonyLikeSender"
}
