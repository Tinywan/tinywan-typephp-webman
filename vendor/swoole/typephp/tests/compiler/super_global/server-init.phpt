--TEST--
$_SERVER: init PHP_SELF, SCRIPT_NAME, SCRIPT_FILENAME, PATH_TRANSLATED and DOCUMENT_ROOT
--ENV--
PHP_SELF=from-environment
SCRIPT_NAME=from-environment
SCRIPT_FILENAME=from-environment
PATH_TRANSLATED=from-environment
DOCUMENT_ROOT=from-environment
--FILE--
<?php

function main()
{
    require __DIR__ . '/../../../src/Assert.php';
    $entryFile = realpath(__FILE__);
    Assert::true(is_string($entryFile));
    Assert::eq($_SERVER['PHP_SELF'], $entryFile);
    Assert::eq($_SERVER['SCRIPT_NAME'], $entryFile);
    Assert::eq($_SERVER['SCRIPT_FILENAME'], $entryFile);
    Assert::eq($_SERVER['PATH_TRANSLATED'], $entryFile);
    Assert::eq($_SERVER['DOCUMENT_ROOT'], '');
}
?>
--EXPECT--
