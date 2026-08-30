--TEST--
Math functions: sin, cos, tan, asin, acos, atan, atan2
--FILE--
<?php
// sin / cos / tan
var_dump(round(sin(0), 10));
var_dump(round(sin(M_PI / 2), 10));
var_dump(round(cos(0), 10));
var_dump(round(cos(M_PI), 10));
var_dump(round(tan(0), 10));
var_dump(round(tan(M_PI / 4), 10));

// asin / acos / atan
var_dump(round(asin(0), 10));
var_dump(round(acos(1), 10));
var_dump(round(atan(0), 10));

// atan2
var_dump(round(atan2(1, 0), 10));
var_dump(round(atan2(0, 1), 10));

// Sinh, cosh, tanh
var_dump(round(sinh(0), 10));
var_dump(round(cosh(0), 10));
var_dump(round(tanh(0), 10));

// asinh, acosh, atanh
var_dump(round(asinh(0), 10));
var_dump(round(acosh(1), 10));
var_dump(round(atanh(0), 10));
?>
--EXPECT--
float(0)
float(1)
float(1)
float(-1)
float(0)
float(1)
float(0)
float(0)
float(0)
float(1.5707963268)
float(0)
float(0)
float(1)
float(0)
float(0)
float(0)
float(0)
