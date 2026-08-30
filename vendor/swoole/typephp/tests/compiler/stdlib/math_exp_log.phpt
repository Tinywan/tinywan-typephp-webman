--TEST--
Math functions: exp, log, sqrt, hypot, deg2rad, rad2deg, pi, fmod, fdiv, fpow
--FILE--
<?php
// pi
var_dump(round(pi(), 10));

// exp / expm1
var_dump(round(exp(0), 10));
var_dump(round(exp(1), 6));
var_dump(round(expm1(0), 10));

// log / log10 / log1p
var_dump(round(log(1), 10));
var_dump(round(log(M_E), 10));
var_dump(round(log10(10), 10));
var_dump(round(log1p(0), 10));

// sqrt
var_dump(round(sqrt(4), 10));
var_dump(round(sqrt(2), 6));

// hypot
var_dump(round(hypot(3, 4), 10));

// deg2rad / rad2deg
var_dump(round(deg2rad(180), 10));
var_dump(round(rad2deg(M_PI), 10));

// fmod
var_dump(round(fmod(5.7, 1.3), 6));

// fdiv
var_dump(round(fdiv(5, 2), 1));

// fpow
var_dump(round(fpow(2, 8), 10));
?>
--EXPECT--
float(3.1415926536)
float(1)
float(2.718282)
float(0)
float(0)
float(1)
float(1)
float(0)
float(2)
float(1.414214)
float(5)
float(3.1415926536)
float(180)
float(0.5)
float(2.5)
float(256)
