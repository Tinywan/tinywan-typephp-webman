--TEST--
Symfony pattern: foreach array destructuring with keyed rows
--FILE--
<?php

function main(): void
{
    $openHandles = [
        'a' => [1, 'handle-a', 'buffer-a', static fn () => 'progress-a'],
        'b' => [2, 'handle-b', 'buffer-b', static fn () => 'progress-b'],
    ];

    foreach ($openHandles as $id => [$pauseExpiry, $handle, $buffer, $onProgress]) {
        var_dump($id.':'.$pauseExpiry.':'.$handle.':'.$buffer.':'.$onProgress());
    }
}
?>
--EXPECT--
string(32) "a:1:handle-a:buffer-a:progress-a"
string(32) "b:2:handle-b:buffer-b:progress-b"
