<?php

namespace TypePhp\Build;

/** A selectively composable part of a Composer native package. */
final readonly class ComposerNativeComponent
{
    /**
     * @param list<string> $sources
     * @param list<string> $defines
     * @param list<string> $requires
     */
    public function __construct(
        public string $name,
        public string $extension,
        public array $sources,
        public array $defines,
        public array $requires,
        public ?string $moduleEntry,
    ) {
    }
}
