--TEST--
StdArray parameter annotations restore checked fixed-shape containers
--FILE--
<?php
use StdArray as MatrixOf;

class ArrayAnnotationUser
{
    public function __construct(public int $id) {}
}

function update_matrix(#[MatrixOf(Type::Int, [2, 3])] box $matrix): void
{
    $matrix[1][2] = 42;
    echo count($matrix), ':', count($matrix[0]), ':', $matrix[1][2], "\n";
}

function update_line(#[StdArray(Type::Int, 2)] $line): void
{
    $line[1] = 9;
}

function read_user(#[StdArray(ArrayAnnotationUser::class, 2)] $users): void
{
    echo $users[1]->id, "\n";
}

function main(): void
{
    $matrix = std::array(std::array(Type::Int, 3), 2);
    update_matrix($matrix);
    var_dump($matrix[1][2]);

    $line = std::array(Type::Int, 2);
    update_line($line);
    var_dump($line[1]);

    $users = std::array(ArrayAnnotationUser::class, 2);
    $users[1] = new ArrayAnnotationUser(7);
    read_user($users);

    $wrongShape = std::array(std::array(Type::Int, 2), 3);
    try {
        update_matrix($wrongShape);
    } catch (TypeError $error) {
        echo "wrong shape\n";
    }

    try {
        update_matrix([[], []]);
    } catch (TypeError $error) {
        echo "not a container\n";
    }
}
?>
--EXPECT--
2:3:42
int(42)
int(9)
7
wrong shape
not a container
