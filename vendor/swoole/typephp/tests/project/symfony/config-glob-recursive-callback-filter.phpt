--TEST--
Symfony Config pattern: RecursiveCallbackFilterIterator and iterator_to_array
--FILE--
<?php

function visible_files(string $root, array $excludedPrefixes): array
{
    $prefixLen = strlen($root) + 1;
    $files = iterator_to_array(new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file, string $path): bool => !isset($excludedPrefixes[$path = str_replace('\\', '/', $path)])
                && (str_ends_with($path, '.php') || $file->isDir())
                && '.' !== $file->getBasename()[0]
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    ));
    uksort($files, 'strnatcmp');

    $relative = [];
    foreach ($files as $path => $info) {
        if ($info->isFile()) {
            $relative[] = substr(str_replace('\\', '/', $path), $prefixLen);
        }
    }

    return $relative;
}

function main(): void
{
    $root = sys_get_temp_dir().'/aot_symfony_glob_case';
    cleanup_glob_fixture($root);
    @mkdir($root.'/src/skip', 0777, true);
    @mkdir($root.'/src/.hidden', 0777, true);
    file_put_contents($root.'/src/App.php', '<?php');
    file_put_contents($root.'/src/App.txt', 'txt');
    file_put_contents($root.'/src/skip/Ignored.php', '<?php');
    file_put_contents($root.'/src/.hidden/Hidden.php', '<?php');

    $excluded = [str_replace('\\', '/', $root.'/src/skip') => true];
    var_dump(visible_files($root, $excluded));
    cleanup_glob_fixture($root);
}

function cleanup_glob_fixture(string $root): void
{
    @unlink($root.'/src/.hidden/Hidden.php');
    @rmdir($root.'/src/.hidden');
    @unlink($root.'/src/skip/Ignored.php');
    @rmdir($root.'/src/skip');
    @unlink($root.'/src/App.php');
    @unlink($root.'/src/App.txt');
    @rmdir($root.'/src');
    @rmdir($root);
}
?>
--CLEAN--
<?php
$root = sys_get_temp_dir().'/aot_symfony_glob_case';
@unlink($root.'/src/.hidden/Hidden.php');
@rmdir($root.'/src/.hidden');
@unlink($root.'/src/skip/Ignored.php');
@rmdir($root.'/src/skip');
@unlink($root.'/src/App.php');
@unlink($root.'/src/App.txt');
@rmdir($root.'/src');
@rmdir($root);
?>
--EXPECT--
array(1) {
  [0]=>
  string(11) "src/App.php"
}
