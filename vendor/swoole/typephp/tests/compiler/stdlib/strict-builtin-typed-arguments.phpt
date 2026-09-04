--TEST--
Optimized builtins preserve strict typed parameter validation
--FILE--
<?php
declare(strict_types=1);

function mixedBool(): mixed
{
    return true;
}

function typedBool(): bool
{
    return true;
}

function typedInt(): int
{
    return 1;
}

function typedFloat(): float
{
    return 1.0;
}

function mixedInt(): mixed
{
    return 1;
}

function mixedArray(): mixed
{
    return [];
}

function unionInt(): bool|int
{
    return 1;
}

function mixedString(): mixed
{
    return '1';
}

function orderedNeedle(array &$events): mixed
{
    $events[] = 'needle';
    return '1';
}

function orderedHaystack(array &$events): mixed
{
    $events[] = 'haystack';
    return [1];
}

function orderedStrict(array &$events): mixed
{
    $events[] = 'strict';
    return true;
}

function main()
{
    var_dump(in_array('1', [1], true));
    var_dump(in_array('1', [1], typedBool()));
    var_dump(in_array('1', [1], mixedBool()));
    var_dump(array_search('1', [1], mixedBool()));
    var_dump(strlen('abc'));
    var_dump(strlen((string) mixedInt()));
    var_dump(hypot(mixedInt(), 0.0));
    var_dump(hypot(typedInt(), 0.0));
    var_dump(hypot(3, 4));
    var_dump(json_decode('"ok"', mixedBool()));
    var_dump(json_decode('null', null));
    var_dump(substr('abc', 1, null));
    var_dump(strlen(json_decode('"abc"', mixedBool())));
    var_dump(array_merge(mixedArray(), ['value' => 1]));
    var_dump(array_keys(mixedArray()));
    var_dump(function_exists(mixedString()));
    var_dump(round(...[1.25, 1]));

    $events = [];
    var_dump(in_array(
        orderedNeedle($events),
        orderedHaystack($events),
        orderedStrict($events)
    ));
    var_dump($events);

    try {
        in_array('1', [1], mixedInt());
        echo "in-array-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "in-array-mixed-int=TypeError\n";
    }

    try {
        in_array('1', [1], typedInt());
        echo "in-array-typed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "in-array-typed-int=TypeError\n";
    }

    try {
        strlen(mixedInt());
        echo "strlen-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strlen-mixed-int=TypeError\n";
    }

    try {
        strlen(null);
        echo "strlen-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strlen-null=TypeError\n";
    }

    try {
        array_fill(mixedString(), 1, 'x');
        echo "array-fill-mixed-string=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-fill-mixed-string=TypeError\n";
    }

    try {
        array_fill(typedFloat(), 1, 'x');
        echo "array-fill-typed-float=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-fill-typed-float=TypeError\n";
    }

    try {
        hypot(0.0, mixedString());
        echo "hypot-mixed-string=missing TypeError\n";
    } catch (TypeError $error) {
        echo "hypot-mixed-string=TypeError\n";
    }

    try {
        json_decode('null', typedInt());
        echo "json-decode-typed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "json-decode-typed-int=TypeError\n";
    }

    try {
        strpos('abc', 'b', null);
        echo "strpos-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "strpos-null=TypeError\n";
    }

    try {
        in_array('1', mixedInt(), true);
        echo "in-array-mixed-haystack=missing TypeError\n";
    } catch (TypeError $error) {
        echo "in-array-mixed-haystack=TypeError\n";
    }

    try {
        is_callable('strlen', mixedInt());
        echo "is-callable-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "is-callable-mixed-int=TypeError\n";
    }

    try {
        array_merge(mixedInt());
        echo "array-merge-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-merge-mixed-int=TypeError\n";
    }

    try {
        array_search('1', [1], mixedArray());
        echo "array-search-mixed-array=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-search-mixed-array=TypeError\n";
    }

    try {
        in_array('1', [1], unionInt());
        echo "in-array-union-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "in-array-union-int=TypeError\n";
    }

    try {
        array_key_exists('key', mixedInt());
        echo "array-key-exists-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-key-exists-mixed-int=TypeError\n";
    }

    try {
        array_keys(mixedInt());
        echo "array-keys-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "array-keys-mixed-int=TypeError\n";
    }

    try {
        round(1.25, mixedString());
        echo "round-mixed-string=missing TypeError\n";
    } catch (TypeError $error) {
        echo "round-mixed-string=TypeError\n";
    }

    try {
        round(1.25, typedFloat());
        echo "round-typed-float=missing TypeError\n";
    } catch (TypeError $error) {
        echo "round-typed-float=TypeError\n";
    }

    try {
        round(1.25, null);
        echo "round-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "round-null=TypeError\n";
    }

    try {
        count([], mixedBool());
        echo "count-mixed-bool=missing TypeError\n";
    } catch (TypeError $error) {
        echo "count-mixed-bool=TypeError\n";
    }

    try {
        count([], typedBool());
        echo "count-typed-bool=missing TypeError\n";
    } catch (TypeError $error) {
        echo "count-typed-bool=TypeError\n";
    }

    try {
        count([], null);
        echo "count-null=missing TypeError\n";
    } catch (TypeError $error) {
        echo "count-null=TypeError\n";
    }

    try {
        function_exists(mixedInt());
        echo "function-exists-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "function-exists-mixed-int=TypeError\n";
    }

    try {
        define(mixedInt(), 1);
        echo "define-mixed-int=missing TypeError\n";
    } catch (TypeError $error) {
        echo "define-mixed-int=TypeError\n";
    }
}
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(false)
int(3)
int(1)
float(1)
float(1)
float(5)
string(2) "ok"
NULL
string(2) "bc"
int(3)
array(1) {
  ["value"]=>
  int(1)
}
array(0) {
}
bool(false)
float(1.3)
bool(false)
array(3) {
  [0]=>
  string(6) "needle"
  [1]=>
  string(8) "haystack"
  [2]=>
  string(6) "strict"
}
in-array-mixed-int=TypeError
in-array-typed-int=TypeError
strlen-mixed-int=TypeError
strlen-null=TypeError
array-fill-mixed-string=TypeError
array-fill-typed-float=TypeError
hypot-mixed-string=TypeError
json-decode-typed-int=TypeError
strpos-null=TypeError
in-array-mixed-haystack=TypeError
is-callable-mixed-int=TypeError
array-merge-mixed-int=TypeError
array-search-mixed-array=TypeError
in-array-union-int=TypeError
array-key-exists-mixed-int=TypeError
array-keys-mixed-int=TypeError
round-mixed-string=TypeError
round-typed-float=TypeError
round-null=TypeError
count-mixed-bool=TypeError
count-typed-bool=TypeError
count-null=TypeError
function-exists-mixed-int=TypeError
define-mixed-int=TypeError
