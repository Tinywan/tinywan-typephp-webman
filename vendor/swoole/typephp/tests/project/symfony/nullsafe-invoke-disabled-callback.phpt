--TEST--
Symfony pattern: nullsafe __invoke callback check
--FILE--
<?php

class SymfonyLikeTraceableDispatcher
{
    public function __construct(private ?Closure $disabled = null)
    {
    }

    public function dispatch(string $name): string
    {
        if ($this->disabled?->__invoke()) {
            return 'disabled';
        }

        return 'dispatch:'.$name;
    }
}

function main(): void
{
    var_dump((new SymfonyLikeTraceableDispatcher())->dispatch('kernel.request'));
    var_dump((new SymfonyLikeTraceableDispatcher(static fn () => false))->dispatch('kernel.response'));
    var_dump((new SymfonyLikeTraceableDispatcher(static fn () => true))->dispatch('kernel.exception'));
}
?>
--EXPECT--
string(23) "dispatch:kernel.request"
string(24) "dispatch:kernel.response"
string(8) "disabled"
