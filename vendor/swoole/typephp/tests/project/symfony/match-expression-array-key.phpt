--TEST--
Symfony pattern: match expression used as array key
--FILE--
<?php

function exportProperty(array &$properties, string $scope, string $name, mixed $value): void
{
    $prefix = $scope[0] ?? '';
    $properties[match ($prefix) {
        "\0" => $scope.$name,
        '*' => "\0*\0".$name,
        default => $name,
    }] = $value;
}

function main(): void
{
    $properties = [];
    exportProperty($properties, 'public', 'name', 'pub');
    exportProperty($properties, '*', 'name', 'prot');
    exportProperty($properties, "\0Private\0", 'name', 'priv');

    foreach ($properties as $key => $value) {
        var_dump(str_replace("\0", '\\0', $key).':'.$value);
    }
}
?>
--EXPECT--
string(8) "name:pub"
string(14) "\0*\0name:prot"
string(20) "\0Private\0name:priv"
