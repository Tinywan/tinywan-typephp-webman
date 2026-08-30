--TEST--
stream_method: 0
--SKIPIF--
--FILE--
<?php
require __DIR__ . '/../../../src/Assert.php';

$filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "test_stream.txt";
$fp = fopen($filepath, "w+");
$rdata = random_bytes(1024);
Assert::greaterThan($fp->write($rdata->base64Encode()), $rdata->length());
$fp->seek(0);
Assert::eq($rdata, $fp->read(8192)->base64Decode());
Assert::eq($fp->stat(), fstat($fp));
Assert::true($fp->sync());
Assert::true($fp->dataSync());
$fp->seek(100);
Assert::true($fp->tell() == 100);
Assert::true($fp->lock(LOCK_SH) == true);
Assert::true($fp->lock(LOCK_UN) == true);
Assert::true($fp->eof() == feof($fp));

$fp->seek(0);
$char = $fp->getChar();
$fp->seek(0);
Assert::eq($char, fgetc($fp));
$fp->seek(0);
$line = $fp->getLine();
$fp->seek(0);
Assert::eq($line, fgets($fp));
Assert::true($fp->truncate(1000));

Assert::true($fp->close());
unlink($filepath);
?>
--EXPECT--
