--TEST--
Symfony pattern: recursive iterator over nested context attributes
--FILE--
<?php

function flattenLeaves(array $attributes): array
{
    $it = new RecursiveIteratorIterator(
        new RecursiveArrayIterator($attributes),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    $result = [];
    foreach ($it as $key => $value) {
        $path = [];
        for ($depth = 0; $depth <= $it->getDepth(); ++$depth) {
            $path[] = $it->getSubIterator($depth)->key();
        }
        $result[implode('.', $path)] = $value;
    }

    ksort($result);
    return $result;
}

function main(): void
{
    $flat = flattenLeaves([
        'groups' => ['Default', 'Extra'],
        'options' => [
            'normalizer' => [
                'trim' => true,
                'lower' => false,
            ],
        ],
    ]);

    foreach ($flat as $key => $value) {
        var_dump($key.'='.json_encode($value));
    }
}
?>
--EXPECT--
string(18) "groups.0="Default""
string(16) "groups.1="Extra""
string(30) "options.normalizer.lower=false"
string(28) "options.normalizer.trim=true"
