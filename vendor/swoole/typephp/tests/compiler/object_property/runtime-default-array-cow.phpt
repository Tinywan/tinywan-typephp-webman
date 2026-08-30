--TEST--
Runtime array property defaults share request templates and separate on write
--FILE--
<?php

class RuntimeArrayCowDefaults
{
    public array $options = [
        'timeout' => 10,
        'headers' => ['Accept' => 'application/json'],
        'list' => [1, 2],
    ];
    public array $tags = ['default'];
}

function mutateDynamicObject(object $object): void
{
    $object->options['headers']['Accept'] = 'text/plain';
}

function main(): void
{
    $first = new RuntimeArrayCowDefaults();
    $second = new RuntimeArrayCowDefaults();
    $third = new RuntimeArrayCowDefaults();

    $first->options['timeout'] = 30;
    $first->options['headers']['X-Test'] = 'one';
    $first->tags[] = 'first';

    $timeout =& $second->options['timeout'];
    $timeout = 20;
    unset($second->options['list'][0]);

    mutateDynamicObject($third);

    $fresh = new RuntimeArrayCowDefaults();
    echo json_encode([$first->options, $first->tags], JSON_UNESCAPED_SLASHES), "\n";
    echo json_encode([$second->options, $second->tags], JSON_UNESCAPED_SLASHES), "\n";
    echo json_encode([$third->options, $third->tags], JSON_UNESCAPED_SLASHES), "\n";
    echo json_encode([$fresh->options, $fresh->tags], JSON_UNESCAPED_SLASHES), "\n";
}
?>
--EXPECT--
[{"timeout":30,"headers":{"Accept":"application/json","X-Test":"one"},"list":[1,2]},["default","first"]]
[{"timeout":20,"headers":{"Accept":"application/json"},"list":{"1":2}},["default"]]
[{"timeout":10,"headers":{"Accept":"text/plain"},"list":[1,2]},["default"]]
[{"timeout":10,"headers":{"Accept":"application/json"},"list":[1,2]},["default"]]
