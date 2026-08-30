--TEST--
An exception from an error handler unwinds an included PHP frame safely
--FILE--
<?php

function throwIncludeWarning(int $severity, string $message, string $file, int $line): never
{
    throw new ErrorException($message, 0, $severity, $file, $line);
}

function main(): void
{
    $file = tempnam(sys_get_temp_dir(), 'typephp-include-unwind-');
    file_put_contents($file, '<?php echo $undefinedIncludeVariable;');
    set_error_handler('throwIncludeWarning');

    try {
        include $file;
    } catch (ErrorException $exception) {
        echo str_contains($exception->getMessage(), 'undefinedIncludeVariable') ? "caught\n" : "wrong exception\n";
    } finally {
        restore_error_handler();
        unlink($file);
    }
}
?>
--EXPECT--
caught
