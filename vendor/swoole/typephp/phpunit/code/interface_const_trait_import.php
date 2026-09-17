<?php
namespace Imported {
    interface Output
    {
        public const VERBOSITY = 32;
    }
}

namespace TraitSource {
    use Imported\Output as Level;

    trait HasLevel
    {
        protected int $verbosity = Level::VERBOSITY;
    }
}

namespace Consumer {
    interface Level
    {
        public const VERBOSITY = 999;
    }

    class Command
    {
        use \TraitSource\HasLevel;
    }
}

namespace {
    function main(): void {}
}
