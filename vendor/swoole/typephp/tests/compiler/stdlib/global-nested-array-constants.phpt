--TEST--
Global nested array constants keep initialization temporaries in separate scopes
--FILE--
<?php
namespace GlobalArrayConstants {

const LIMIT = 42;
const FIRST = [[[LIMIT, 1]], [[2, 3]]];
const SECOND = [0 => ['left' => LIMIT], 'tail' => [2, [3]]];
const THIRD = [FIRST, SECOND];
}

namespace {
function main(): void
{
    var_dump(GlobalArrayConstants\FIRST[0][0][0]);
    var_dump(GlobalArrayConstants\FIRST[1][0][1]);
    var_dump(GlobalArrayConstants\SECOND[0]['left']);
    var_dump(GlobalArrayConstants\SECOND['tail'][1][0]);
    var_dump(GlobalArrayConstants\THIRD[0] === GlobalArrayConstants\FIRST);
    var_dump(GlobalArrayConstants\THIRD[1] === GlobalArrayConstants\SECOND);
}
}
?>
--EXPECT--
int(42)
int(3)
int(42)
int(3)
bool(true)
bool(true)
