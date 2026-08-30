--TEST--
JSON optimized functions preserve PHP exceptions and option semantics
--FILE--
<?php

function main(): void
{
    try {
        json_decode('{broken', true, 512, JSON_THROW_ON_ERROR);
        echo "decode-not-caught\n";
    } catch (JsonException $exception) {
        echo "decode-caught\n";
    }

    $recursive = [];
    $recursive['self'] = &$recursive;
    try {
        json_encode($recursive, JSON_THROW_ON_ERROR);
        echo "encode-not-caught\n";
    } catch (JsonException $exception) {
        echo "encode-caught\n";
    }

    var_dump(json_decode('{broken') === null);
    var_dump(json_last_error() === JSON_ERROR_SYNTAX);
    var_dump(json_decode('{"value":1}') instanceof stdClass);
    var_dump(json_last_error() === JSON_ERROR_NONE);

    $object = json_decode('{"value":1}', false, 512, JSON_OBJECT_AS_ARRAY);
    var_dump($object instanceof stdClass);

    try {
        json_decode('{}', true, 0);
        echo "depth-not-caught\n";
    } catch (ValueError $exception) {
        echo "depth-caught\n";
    }

}

?>
--EXPECT--
decode-caught
encode-caught
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
depth-caught
