--TEST--
Symfony pattern: static closure normalizer returns another static closure
--FILE--
<?php

function buildTranslator(string $domain): Closure
{
    $normalizer = static fn (string $message): Closure => static fn () => '['.$domain.'] '.$message;

    return $normalizer('upload failed');
}

function main(): void
{
    $message = buildTranslator('validators');
    var_dump($message());
}
?>
--EXPECT--
string(26) "[validators] upload failed"
