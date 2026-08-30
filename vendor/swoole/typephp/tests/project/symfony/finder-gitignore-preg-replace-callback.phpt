--TEST--
Symfony Finder Gitignore pattern: preg_replace_callback with static arrow function
--FILE--
<?php
function normalizeGitignoreCharacterClass(string $regex): string
{
    return preg_replace_callback(
        '~\\\\\[((?:\\\\!)?)([^\[\]]*)\\\\\]~',
        static fn (array $matches): string => '['.('' !== $matches[1] ? '^' : '').str_replace('\\-', '-', $matches[2]).']',
        $regex
    );
}

function main(): void
{
    var_dump(normalizeGitignoreCharacterClass('\\[a\\-z\\]'));
    var_dump(normalizeGitignoreCharacterClass('\\[\\!0\\-9\\]'));
    var_dump(normalizeGitignoreCharacterClass('plain'));
}
?>
--EXPECT--
string(5) "[a-z]"
string(6) "[^0-9]"
string(5) "plain"
