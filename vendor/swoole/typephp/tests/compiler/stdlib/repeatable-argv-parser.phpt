--TEST--
repeatable argv parser with separated equals and compact short flags
--FILE--
<?php

function is_long_flag_with_equals(string $arg, array $flags, array &$values): bool
{
    foreach ($flags as $flag) {
        if (str_starts_with($arg, $flag . '=')) {
            $values[] = substr($arg, strlen($flag) + 1);
            return true;
        }
    }
    return false;
}

function parse_repeatable_argv(array $argv, array $flags): array
{
    $values = [];
    for ($i = 1; $i < count($argv); $i++) {
        if (in_array($argv[$i], $flags, true) && isset($argv[$i + 1]) && $argv[$i + 1] !== '' && $argv[$i + 1][0] !== '-') {
            $values[] = $argv[$i + 1];
            $i++;
        } elseif (!is_long_flag_with_equals($argv[$i], $flags, $values)) {
            foreach ($flags as $flag) {
                if (strlen($flag) === 2 && $flag[0] === '-') {
                    $short = substr($flag, 1);
                    if (preg_match('/^-' . preg_quote($short, '/') . '(.+)$/', $argv[$i], $m)) {
                        $values[] = $m[1];
                    }
                }
            }
        }
    }
    return $values;
}

function main(): void
{
    $argv = [
        'compiler.php',
        '-I', 'include',
        '--include-path=/opt/include',
        '-Irelative',
        '-I', '-not-a-value',
        '--include-path', '',
        '--other', 'skip',
    ];

    var_dump(parse_repeatable_argv($argv, ['-I', '--include-path']));
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(7) "include"
  [1]=>
  string(12) "/opt/include"
  [2]=>
  string(8) "relative"
}
