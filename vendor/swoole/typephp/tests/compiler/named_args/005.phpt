--TEST--
Named Arguments - PHP 8+ function call syntax
--FILE--
<?php
// Test mixed positional and named
function format_string($prefix, $content, $suffix = '') {
    return $prefix . $content . $suffix;
}

function main() {
    // Test mixed positional and named (positional must come first)
    var_dump(format_string('[', 'content', ']'));
    var_dump(format_string('[', 'content', suffix: ')'));
    var_dump(format_string('<<', 'data', suffix: '>>'));
}
?>
--EXPECT--
string(9) "[content]"
string(9) "[content)"
string(8) "<<data>>"
