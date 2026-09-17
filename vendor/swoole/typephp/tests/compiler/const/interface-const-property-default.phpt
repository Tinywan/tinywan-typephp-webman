--TEST--
Interface constants can initialize properties composed from a trait
--FILE--
<?php
namespace Issue112 {
    interface BaseOutputInterface
    {
        public const VERBOSITY_QUIET = 16;
    }

    interface OutputInterface extends BaseOutputInterface
    {
        public const VERBOSITY_NORMAL = 32;
        public const VERBOSITY_VERBOSE = 64;
        public const VERBOSITY_VERY_VERBOSE = 128;
        public const VERBOSITY_DEBUG = 256;
    }

    trait InteractsWithIO
    {
        protected int $verbosity = OutputInterface::VERBOSITY_NORMAL;

        protected array $verbosityMap = [
            'v' => OutputInterface::VERBOSITY_VERBOSE,
            'vv' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            'vvv' => OutputInterface::VERBOSITY_DEBUG,
            'quiet' => OutputInterface::VERBOSITY_QUIET,
            'normal' => OutputInterface::VERBOSITY_NORMAL,
        ];

        public function getVerbosity(): int
        {
            return $this->verbosity;
        }

        public function getVerbosityMap(): array
        {
            return $this->verbosityMap;
        }
    }

    class Command
    {
        use InteractsWithIO;
    }
}

namespace {
    function main(): void
    {
        $command = new Issue112\Command();
        var_dump($command->getVerbosity());
        var_dump($command->getVerbosityMap());
    }
}
?>
--EXPECT--
int(32)
array(5) {
  ["v"]=>
  int(64)
  ["vv"]=>
  int(128)
  ["vvv"]=>
  int(256)
  ["quiet"]=>
  int(16)
  ["normal"]=>
  int(32)
}
