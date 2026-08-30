--TEST--
Symfony pattern: match array filtered then unioned with base arguments
--FILE--
<?php

function schedulerArgs(array $tagAttributes, string $serviceId): array
{
    return [
        '$message' => $serviceId,
    ] + array_filter(match ($tagAttributes['trigger'] ?? throw new InvalidArgumentException(sprintf('missing trigger for "%s"', $serviceId))) {
        'every' => [
            '$frequency' => $tagAttributes['frequency'] ?? throw new InvalidArgumentException(sprintf('missing frequency for "%s"', $serviceId)),
            '$from' => $tagAttributes['from'] ?? null,
            '$until' => $tagAttributes['until'] ?? null,
        ],
        'cron' => [
            '$expression' => $tagAttributes['expression'] ?? throw new InvalidArgumentException(sprintf('missing expression for "%s"', $serviceId)),
            '$timezone' => $tagAttributes['timezone'] ?? null,
        ],
    }, static fn ($value) => null !== $value);
}

function main(): void
{
    var_dump(schedulerArgs(['trigger' => 'every', 'frequency' => '1 hour', 'from' => null], 'task.one'));
    var_dump(schedulerArgs(['trigger' => 'cron', 'expression' => '* * * * *', 'timezone' => 'UTC'], 'task.two'));
}
?>
--EXPECT--
array(2) {
  ["$message"]=>
  string(8) "task.one"
  ["$frequency"]=>
  string(6) "1 hour"
}
array(3) {
  ["$message"]=>
  string(8) "task.two"
  ["$expression"]=>
  string(9) "* * * * *"
  ["$timezone"]=>
  string(3) "UTC"
}
