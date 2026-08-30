--TEST--
WASI filesystem supports PHP stream read, write and directory operations
--FILE--
<?php
function main(): void
{
    $directory = '/sandbox/typephp';
    $file = $directory . '/message.txt';
    if (!is_dir($directory)) {
        mkdir($directory);
    }
    file_put_contents($file, "hello filesystem\n");
    echo file_get_contents($file);
    var_dump(in_array('message.txt', scandir($directory), true));
    unlink($file);
    rmdir($directory);
    var_dump(file_exists($file));
}
?>
--EXPECT--
hello filesystem
bool(true)
bool(false)
