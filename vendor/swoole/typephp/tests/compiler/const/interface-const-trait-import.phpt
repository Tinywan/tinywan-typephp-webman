--TEST--
Imported interface constants in trait defaults keep the trait's lexical namespace
--FILE--
<?php
namespace Source {
    interface OutputInterface
    {
        public const VERBOSITY_NORMAL = 32;
        public const VERBOSITY_VERBOSE = 64;
    }
}

namespace Traits {
    use Source\OutputInterface as Output;

    trait HasOutput
    {
        protected int $verbosity = Output::VERBOSITY_NORMAL;
        protected array $levels = ['verbose' => Output::VERBOSITY_VERBOSE];

        public function verbosity(): int { return $this->verbosity; }
        public function levels(): array { return $this->levels; }
    }
}

namespace Wrapper {
    use Traits\HasOutput;

    trait WrappedOutput
    {
        use HasOutput;
    }
}

namespace Local {
    use Source\OutputInterface;

    trait HasLocalOutput
    {
        protected int $verbosity = OutputInterface::VERBOSITY_NORMAL;

        public function verbosity(): int { return $this->verbosity; }
    }

    class Command
    {
        use HasLocalOutput;
    }
}

namespace Consumer {
    class Command
    {
        use \Wrapper\WrappedOutput;
    }
}

namespace {
    function main(): void
    {
        $command = new Consumer\Command();
        var_dump($command->verbosity());
        var_dump($command->levels());
        var_dump((new Local\Command())->verbosity());
    }
}
?>
--EXPECT--
int(32)
array(1) {
  ["verbose"]=>
  int(64)
}
int(32)
