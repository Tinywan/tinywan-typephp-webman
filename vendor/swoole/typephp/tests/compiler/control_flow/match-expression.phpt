--TEST--
Match Expression - PHP 8+ enhanced switch statement
--FILE--
<?php
function test_basic_match($value) {
    return match($value) {
        1 => 'one',
        2 => 'two',
        3 => 'three',
        default => 'other',
    };
}

function test_multiple_conditions($status) {
    return match($status) {
        200, 201, 202 => 'success',
        400, 401, 403, 404 => 'client_error',
        500, 501, 502, 503 => 'server_error',
        default => 'unknown',
    };
}

function test_strict_comparison($value) {
    return match($value) {
        0 => 'integer zero',
        '0' => 'string zero',
        false => 'boolean false',
        null => 'null value',
        default => 'other',
    };
}

function test_expression_result($a, $b, $op) {
    return match($op) {
        'add' => $a + $b,
        'sub' => $a - $b,
        'mul' => $a * $b,
        'div' => $b !== 0 ? $a / $b : 'error',
        default => 'invalid operation',
    };
}

function test_nested_match($level) {
    return match($level) {
        1 => match(true) {
            true => 'level 1 - true',
            false => 'level 1 - false',
        },
        2 => 'level 2',
        default => 'other level',
    };
}

function test_no_default($value) {
    try {
        return match($value) {
            1 => 'one',
            2 => 'two',
        };
    } catch (UnhandledMatchError $e) {
        return "Error: " . $e->getMessage();
    }
}

function main() {
    // Test basic match
    var_dump(test_basic_match(1));
    var_dump(test_basic_match(2));
    var_dump(test_basic_match(5));
    
    // Test multiple conditions
    var_dump(test_multiple_conditions(200));
    var_dump(test_multiple_conditions(404));
    var_dump(test_multiple_conditions(500));
    var_dump(test_multiple_conditions(999));
    
    // Test strict comparison
    var_dump(test_strict_comparison(0));
    var_dump(test_strict_comparison('0'));
    var_dump(test_strict_comparison(false));
    var_dump(test_strict_comparison(null));
    
    // Test expression result
    var_dump(test_expression_result(10, 5, 'add'));
    var_dump(test_expression_result(10, 5, 'sub'));
    var_dump(test_expression_result(10, 5, 'mul'));
    var_dump(test_expression_result(10, 5, 'div'));
    var_dump(test_expression_result(10, 0, 'div'));
    
    // Test nested match
    var_dump(test_nested_match(1));
    var_dump(test_nested_match(2));
    var_dump(test_nested_match(3));
    
    // Test no default (should throw error)
    var_dump(test_no_default(1));
    var_dump(test_no_default(5));
}
?>
--EXPECT--
string(3) "one"
string(3) "two"
string(5) "other"
string(7) "success"
string(12) "client_error"
string(12) "server_error"
string(7) "unknown"
string(12) "integer zero"
string(11) "string zero"
string(13) "boolean false"
string(10) "null value"
int(15)
int(5)
int(50)
int(2)
string(5) "error"
string(14) "level 1 - true"
string(7) "level 2"
string(11) "other level"
string(3) "one"
string(27) "Error: Unhandled match case"
