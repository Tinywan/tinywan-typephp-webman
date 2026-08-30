--TEST--
WASM runtime contains the portable PHP extension set
--FILE--
<?php
function main(): void
{
    $loaded = array_fill_keys(get_loaded_extensions(), true);
    foreach (['Core', 'date', 'ctype', 'calendar', 'bcmath', 'filter', 'tokenizer', 'mbstring', 'zlib', 'fileinfo'] as $extension) {
        echo $extension, '=', isset($loaded[$extension]) ? 'yes' : 'no', "\n";
    }
}
?>
--EXPECT--
Core=yes
date=yes
ctype=yes
calendar=yes
bcmath=yes
filter=yes
tokenizer=yes
mbstring=yes
zlib=yes
fileinfo=yes
