--TEST--
Symfony Console pattern: match(true) returns arrays and array_map option expansion
--FILE--
<?php

final class InputOption
{
    public function __construct(
        private bool $negatable,
        private bool $acceptValue,
        private bool $array,
    ) {
    }

    public function isNegatable(): bool
    {
        return $this->negatable;
    }

    public function acceptValue(): bool
    {
        return $this->acceptValue;
    }

    public function isArray(): bool
    {
        return $this->array;
    }
}

function unparse_options(array $rawOptions, array $definition): array
{
    $unparsedOptions = [];

    foreach ($rawOptions as $optionName => $parsedOption) {
        $option = $definition[$optionName];

        $unparsedOptions[] = match (true) {
            $option->isNegatable() => [sprintf('--%s%s', $parsedOption ? '' : 'no-', $optionName)],
            !$option->acceptValue() => [sprintf('--%s', $optionName)],
            $option->isArray() => array_map(static fn ($item) => sprintf('--%s=%s', $optionName, $item), $parsedOption),
            default => [sprintf('--%s=%s', $optionName, $parsedOption)],
        };
    }

    return array_merge(...$unparsedOptions);
}

function main(): void
{
    $definition = [
        'ansi' => new InputOption(true, false, false),
        'verbose' => new InputOption(false, false, false),
        'tag' => new InputOption(false, true, true),
        'env' => new InputOption(false, true, false),
    ];

    var_dump(unparse_options([
        'ansi' => false,
        'verbose' => true,
        'tag' => ['api', 'worker'],
        'env' => 'prod',
    ], $definition));
}
?>
--EXPECT--
array(5) {
  [0]=>
  string(9) "--no-ansi"
  [1]=>
  string(9) "--verbose"
  [2]=>
  string(9) "--tag=api"
  [3]=>
  string(12) "--tag=worker"
  [4]=>
  string(10) "--env=prod"
}
