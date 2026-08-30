<?php

const C_I = 11;
const C_F = 1.1;
const C_S = "str";
const C_B = true;
const C_N = null;
const C_A = [1, 2, 3];
const C_O = new stdClass();

const C_I2 = -C_I;
const C_F2 = C_F * 2;

function main()
{
    var_dump(get_defined_constants());
}

