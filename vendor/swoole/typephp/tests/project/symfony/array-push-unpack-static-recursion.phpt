--TEST--
Symfony pattern: array_push with unpacked static method result
--FILE--
<?php

class FunctionCallParser
{
    public static function parse(string $expr): array
    {
        $calls = [];

        foreach (explode(',', $expr) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $calls[] = strtoupper($part);
            if (str_contains($part, '+')) {
                array_push($calls, ...self::parse(str_replace('+', ',', $part)));
            }
        }

        return $calls;
    }
}

function main(): void
{
    var_dump(FunctionCallParser::parse('foo,bar+baz'));
}
?>
--EXPECT--
array(4) {
  [0]=>
  string(3) "FOO"
  [1]=>
  string(7) "BAR+BAZ"
  [2]=>
  string(3) "BAR"
  [3]=>
  string(3) "BAZ"
}
