--TEST--
Symfony Serializer pattern: isset multiple trace keys and compact return
--FILE--
<?php

function normalize_trace_frame(array $trace, int $i): array
{
    $name = 'unknown';
    $file = null;
    $line = null;

    if (isset($trace[$i]['class'], $trace[$i]['function'])) {
        $name = $trace[$i]['class'].'::'.$trace[$i]['function'];
    } elseif (isset($trace[$i]['function'])) {
        $name = $trace[$i]['function'];
    }

    if (isset($trace[$i]['file'], $trace[$i]['line'])) {
        $file = $trace[$i]['file'];
        $line = $trace[$i]['line'];
    }

    return compact('name', 'file', 'line');
}

function main(): void
{
    $trace = [
        ['class' => 'Serializer', 'function' => 'normalize', 'file' => 'TraceableSerializer.php', 'line' => 170],
        ['function' => 'main'],
    ];

    var_dump(normalize_trace_frame($trace, 0));
    var_dump(normalize_trace_frame($trace, 1));
}
?>
--EXPECT--
array(3) {
  ["name"]=>
  string(21) "Serializer::normalize"
  ["file"]=>
  string(23) "TraceableSerializer.php"
  ["line"]=>
  int(170)
}
array(3) {
  ["name"]=>
  string(4) "main"
  ["file"]=>
  NULL
  ["line"]=>
  NULL
}
