--TEST--
array_search keys with array_slice and array_reverse preserving keys
--FILE--
<?php

function select_version_flags(array $flagsByVersions, int $minVersion, ?int $maxVersion): array
{
    ksort($flagsByVersions);

    $index = array_search($minVersion, array_keys($flagsByVersions));
    if ($index === false) {
        return [];
    }
    $flagsByVersions = array_slice($flagsByVersions, $index, null, true);

    if ($maxVersion !== null) {
        $index = array_search($maxVersion, array_keys($flagsByVersions));
        if ($index === false) {
            return [];
        }
        $flagsByVersions = array_slice($flagsByVersions, 0, $index + 1, true);
    }

    $result = [];
    foreach (array_reverse($flagsByVersions, true) as $version => $flags) {
        $result[] = $version . ':' . implode('|', $flags);
    }
    return $result;
}

function main(): void
{
    $flags = [
        80400 => ['D'],
        80000 => ['A'],
        80200 => ['B'],
        80300 => ['C'],
    ];

    var_dump(select_version_flags($flags, 80200, 80400));
    var_dump(select_version_flags($flags, 80100, null));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(7) "80400:D"
  [1]=>
  string(7) "80300:C"
  [2]=>
  string(7) "80200:B"
}
array(0) {
}
