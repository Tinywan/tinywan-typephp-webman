--TEST--
Symfony ErrorHandler pattern: Stringable values in destructured log context
--FILE--
<?php

final class ContextValue implements Stringable
{
    public function __construct(private string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

function normalize_logs(array $logs): array
{
    $normalized = [];

    foreach ($logs as [$level, $message, $context]) {
        foreach ($context as $key => $val) {
            if (null === $val || is_scalar($val) || $val instanceof Stringable) {
                $context[$key] = (string) $val;
            }
        }

        $normalized[] = [$level, $message, $context];
    }

    return $normalized;
}

function main(): void
{
    var_dump(normalize_logs([
        ['info', 'boot', ['request' => new ContextValue('GET /'), 'count' => 2, 'skip' => []]],
    ]));
}
?>
--EXPECT--
array(1) {
  [0]=>
  array(3) {
    [0]=>
    string(4) "info"
    [1]=>
    string(4) "boot"
    [2]=>
    array(3) {
      ["request"]=>
      string(5) "GET /"
      ["count"]=>
      string(1) "2"
      ["skip"]=>
      array(0) {
      }
    }
  }
}
