--TEST--
any
--FILE--
<?php
function main()
{
    // heredoc with different white space characters
    $heredoc_escchar = <<<EOT6
    This checks\t chunk_split()\nEscape\nchars
EOT6;

    var_dump(chunk_split($heredoc_escchar, strlen($heredoc_escchar)) );
}
?>
--EXPECT--
string(45) "    This checks	 chunk_split()
Escape
chars
"