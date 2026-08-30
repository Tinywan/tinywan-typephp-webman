--TEST--
resource version normalization with ltrim explode preg_replace and slice
--FILE--
<?php

function normalize_resource_version(string $version): string
{
    $version = ltrim($version, 'vV');

    if (str_contains($version, ',')) {
        return $version;
    }

    $parts = explode('.', $version);
    foreach ($parts as $i => $part) {
        $parts[$i] = preg_replace('/[^0-9]/', '', $part) ?: '0';
    }

    while (count($parts) < 4) {
        $parts[] = '0';
    }

    return implode(',', array_slice($parts, 0, 4));
}

function main(): void
{
    var_dump(normalize_resource_version('v1.2.3'));
    var_dump(normalize_resource_version('V10.beta.5-rc1.7.9'));
    var_dump(normalize_resource_version('1,2,3,4'));
}
?>
--EXPECT--
string(7) "1,2,3,0"
string(9) "10,0,51,7"
string(7) "1,2,3,4"
