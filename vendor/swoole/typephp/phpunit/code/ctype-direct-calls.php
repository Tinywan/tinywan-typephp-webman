<?php

function ctypeDirectCalls(mixed $char): bool
{
    $result = ctype_alnum($char);
    $result = ctype_alpha($char) && $result;
    $result = ctype_cntrl($char) && $result;
    $result = ctype_digit($char) && $result;
    $result = ctype_lower($char) && $result;
    $result = ctype_graph($char) && $result;
    $result = ctype_print($char) && $result;
    $result = ctype_punct($char) && $result;
    $result = ctype_space($char) && $result;
    $result = ctype_upper($char) && $result;
    return ctype_xdigit(text: $char) && $result;
}
