--TEST--
Symfony pattern: array_reduce builds directive string from keyed arrays
--FILE--
<?php

class SymfonyLikeContentSecurityPolicyHandler
{
    public function compileDirectives(array $directives): string
    {
        return array_reduce(
            array_keys($directives),
            static fn ($result, $name) => ('' !== $result ? $result.'; ' : '').sprintf('%s %s', $name, implode(' ', $directives[$name])),
            ''
        );
    }
}

function main(): void
{
    $handler = new SymfonyLikeContentSecurityPolicyHandler();

    var_dump($handler->compileDirectives([
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'cdn.example.test'],
    ]));
}
?>
--EXPECT--
string(54) "default-src 'self'; script-src 'self' cdn.example.test"
