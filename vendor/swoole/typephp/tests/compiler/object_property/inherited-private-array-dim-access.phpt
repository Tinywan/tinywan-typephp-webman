--TEST--
Inherited instance uses the declaring class private array property for direct and coalesce reads
--FILE--
<?php

abstract class SynopsisCommand
{
    private string $name = 'test:command';
    private array $synopsis = [];

    public function getSynopsis(bool $short = false): string
    {
        $key = $short ? 'short' : 'long';
        if (!isset($this->synopsis[$key])) {
            $this->synopsis[$key] = trim(sprintf(
                '%s %s',
                $this->name,
                $short ? '[options]' : '[--help] [options]'
            ));
        }

        return $this->synopsis[$key];
    }

    public function getSynopsisWithFallback(bool $short = false): string
    {
        $key = $short ? 'short' : 'long';
        return $this->synopsis[$key] ?? '';
    }

    public function getAllSynopsis(): array
    {
        return $this->synopsis;
    }
}

final class ConcreteSynopsisCommand extends SynopsisCommand
{
}

function main(): void
{
    $command = new ConcreteSynopsisCommand();

    echo $command->getSynopsis(true), "\n";
    echo $command->getSynopsis(false), "\n";
    echo $command->getSynopsisWithFallback(true), "\n";
    echo $command->getSynopsisWithFallback(false), "\n";
    var_dump($command->getAllSynopsis());
}
?>
--EXPECT--
test:command [options]
test:command [--help] [options]
test:command [options]
test:command [--help] [options]
array(2) {
  ["short"]=>
  string(22) "test:command [options]"
  ["long"]=>
  string(31) "test:command [--help] [options]"
}
