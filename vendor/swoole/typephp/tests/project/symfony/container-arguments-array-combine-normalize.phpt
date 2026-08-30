--TEST--
Symfony DI pattern: normalize named arguments with array_combine and preg_replace
--FILE--
<?php

function normalizeArguments(array $arguments): array
{
    return array_combine(
        array_map(static fn ($key) => preg_replace('/^.*\$/', '', $key), array_keys($arguments)),
        $arguments,
    );
}

function main(): void
{
    var_dump(normalizeArguments([
        'App\Service $mailer' => 'smtp',
        '$logger' => 'file',
        'plain' => 'value',
    ]));
}
?>
--EXPECT--
array(3) {
  ["mailer"]=>
  string(4) "smtp"
  ["logger"]=>
  string(4) "file"
  ["plain"]=>
  string(5) "value"
}
