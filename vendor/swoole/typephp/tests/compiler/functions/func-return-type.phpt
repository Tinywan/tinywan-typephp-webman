--TEST--
static calls
--FILE--
<?php

function test(): void {
    set_error_handler(static function (int $code, string $msg, string $file, int $line): bool {
        return true;
    });
    var_dump(__FUNCTION__);
}

function main() {
      $fn = 'test';
      $fn();
}
?>
--EXPECT--
string(4) "test"