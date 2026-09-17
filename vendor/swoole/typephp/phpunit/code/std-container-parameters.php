<?php
use StdVector as VectorOf;
use StdArray as MatrixOf;

class StdParameterUser
{
    public function __construct(public int $id) {}
}

function std_parameter_vector(#[VectorOf(Type::Int)] $vec): int
{
    $vec[] = 42;
    return $vec[0];
}

function std_parameter_matrix(#[MatrixOf(Type::Int, [2, 3])] box $matrix): int
{
    $matrix[1][2] = 42;
    return $matrix[1][2];
}

function std_parameter_map(#[StdMap(Type::String, Type::Float)] $map): float
{
    $map['value'] = 2.5;
    return $map['value'];
}

class StdParameterReceiver
{
    public function accept(#[StdOrderedMap(Type::Int, StdParameterUser::class)] $users): int
    {
        return $users[0]->id;
    }
}

function main(): void
{
    $vec = std::vector(Type::Int);
    $vec[] = 7;
    var_dump(std_parameter_vector($vec));
}
