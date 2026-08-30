--TEST--
File Operations - File read/write, directory operations
--FILE--
<?php

// Test creating a temporary directory
$tempDir = sys_get_temp_dir() . '/aot_test_' . uniqid();
mkdir($tempDir);

// Verify directory was created
if (is_dir($tempDir)) {
    echo "Directory created: " . $tempDir . "\n";
}

// Test creating and writing to a file
$testFile = $tempDir . '/test.txt';
$content = "Hello, World!\nThis is a test file.\n";
file_put_contents($testFile, $content);

// Verify file was created and check its content
if (file_exists($testFile)) {
    echo "File created: " . $testFile . "\n";
    
    // Read the file content
    $readContent = file_get_contents($testFile);
    echo "File content:\n" . $readContent;
    
    // Check file size
    $fileSize = filesize($testFile);
    echo "File size: " . $fileSize . " bytes\n";
}

// Test appending to file
$appendContent = "Appended line\n";
file_put_contents($testFile, $appendContent, FILE_APPEND | LOCK_EX);
$updatedContent = file_get_contents($testFile);
echo "Content after append:\n" . $updatedContent;

// Test file reading functions
$lines = file($testFile, FILE_IGNORE_NEW_LINES);
echo "Number of lines: " . count($lines) . "\n";

// Test file permissions
if (is_readable($testFile)) {
    echo "File is readable\n";
}
if (is_writable($testFile)) {
    echo "File is writable\n";
}

// Test directory listing
$anotherFile = $tempDir . '/second_test.txt';
file_put_contents($anotherFile, "Another test file");

$files = scandir($tempDir);
echo "Directory contents:\n";
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "- " . $file . "\n";
    }
}

// Test file operations with file handles
$handle = fopen($testFile, 'r');
if ($handle) {
    $firstLine = fgets($handle);
    echo "First line from handle: " . trim($firstLine) . "\n";
    fclose($handle);
}

// Test file copy
$copiedFile = $tempDir . '/copied_test.txt';
if (copy($testFile, $copiedFile)) {
    echo "File copied to: " . $copiedFile . "\n";
}

// Test file move/rename
$movedFile = $tempDir . '/moved_test.txt';
if (rename($copiedFile, $movedFile)) {
    echo "File moved to: " . $movedFile . "\n";
}

// Test checking if it's a file or directory
echo "Is test file a file: " . (is_file($testFile) ? "yes" : "no") . "\n";
echo "Is temp dir a directory: " . (is_dir($tempDir) ? "yes" : "no") . "\n";

// Test getting file modification time
$modTime = filemtime($testFile);
echo "File modification time: " . date('Y-m-d H:i:s', $modTime) . "\n";

// Clean up - remove files and directory
unlink($testFile);
unlink($anotherFile);
unlink($movedFile);
rmdir($tempDir);

echo "Cleanup completed\n";
?>
--EXPECTF--
Directory created: %s
File created: %s/test.txt
File content:
Hello, World!
This is a test file.
File size: 35 bytes
Content after append:
Hello, World!
This is a test file.
Appended line
Number of lines: 3
File is readable
File is writable
Directory contents:
- second_test.txt
- test.txt
First line from handle: Hello, World!
File copied to: %s/copied_test.txt
File moved to: %s/moved_test.txt
Is test file a file: yes
Is temp dir a directory: yes
File modification time: %d-%d-%d %d:%d:%d
Cleanup completed
