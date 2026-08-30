--TEST--
preprocessor comment condition stack
--FILE--
<?php

function handle_preprocessor_conditions(array &$conds, array $comments): ?string
{
    foreach ($comments as $comment) {
        $text = trim($comment);
        if (preg_match('/^#\s*if\s+(.+)$/', $text, $matches)) {
            $conds[] = $matches[1];
        } elseif (preg_match('/^#\s*ifdef\s+(.+)$/', $text, $matches)) {
            $conds[] = "defined($matches[1])";
        } elseif (preg_match('/^#\s*ifndef\s+(.+)$/', $text, $matches)) {
            $conds[] = "!defined($matches[1])";
        } elseif (preg_match('/^#\s*else$/', $text)) {
            if (empty($conds)) {
                return 'else-error';
            }
            $cond = array_pop($conds);
            $conds[] = "!($cond)";
        } elseif (preg_match('/^#\s*endif$/', $text)) {
            if (empty($conds)) {
                return 'endif-error';
            }
            array_pop($conds);
        } elseif ($text !== '' && $text[0] === '#') {
            return "unknown:$text";
        }
    }

    return empty($conds) ? null : implode(' && ', $conds);
}

function main(): void
{
    $conds = [];
    var_dump(handle_preprocessor_conditions($conds, ['#ifdef PHP_WIN32', '#if PHP_VERSION_ID >= 80400']));
    var_dump(handle_preprocessor_conditions($conds, ['#else']));
    var_dump(handle_preprocessor_conditions($conds, ['#endif', '#endif']));
    var_dump(handle_preprocessor_conditions($conds, ['#else']));
    var_dump(handle_preprocessor_conditions($conds, ['#pragma once']));
}
?>
--EXPECT--
string(45) "defined(PHP_WIN32) && PHP_VERSION_ID >= 80400"
string(48) "defined(PHP_WIN32) && !(PHP_VERSION_ID >= 80400)"
NULL
string(10) "else-error"
string(20) "unknown:#pragma once"
