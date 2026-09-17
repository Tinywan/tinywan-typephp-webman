<?php

namespace TypePhp\Build;

/** Result of resolving compiler usage to php-nano extension components. */
final readonly class NanoExtensionSelection
{
    /**
     * @param list<string> $extensions
     * @param list<string> $features Fine-grained capabilities within large extensions.
     * @param list<string> $reasons
     */
    public function __construct(
        public array $extensions,
        public bool $completeFallback,
        public array $features = [],
        public array $reasons = [],
    ) {
    }
}
