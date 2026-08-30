--TEST--
Float edge cases: NAN, INF, -INF
--FILE--
<?php
function main(): void {
    error_reporting(E_ERROR);
    var_dump(NAN);
    var_dump(INF);
    var_dump(-INF);
    var_dump(is_nan(NAN));
    var_dump(is_nan(0.0));
    var_dump(is_infinite(INF));
    var_dump(is_infinite(-INF));
    var_dump(is_infinite(1.5));
    var_dump(is_finite(INF));
    var_dump(is_finite(1.5));
    // Keep the NAN-to-string conversion at runtime so error_reporting() can
    // suppress PHP 8.5's new warning in this cross-version value test.
    $nan = NAN;
    echo $nan . "\n";
    echo INF . "\n";
    echo -INF . "\n";
    var_dump(INF + INF);
    var_dump(INF / INF);
}
?>
--EXPECT--
float(NAN)
float(INF)
float(-INF)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(false)
bool(true)
NAN
INF
-INF
float(INF)
float(NAN)
