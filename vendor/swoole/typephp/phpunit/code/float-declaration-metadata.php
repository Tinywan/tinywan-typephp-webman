<?php

class FloatDeclarationContainer
{
    public const float POSITIVE_INF = INF;
    public const float NEGATIVE_INF = -INF;
    public const float NOT_A_NUMBER = NAN;
    public const float CONST_E = M_E;
    public const float CONST_ONE_POINT_FIVE = 1.5;

    public float $property_e = M_E;
    public float $property_inf = INF;
    public float $property_nan = NAN;
    public float $property_one_point_five = 1.5;
}

function main(): void
{
}
