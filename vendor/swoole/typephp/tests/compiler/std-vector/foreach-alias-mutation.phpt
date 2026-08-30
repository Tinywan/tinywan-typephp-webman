--TEST--
std containers: foreach structural mutation through aliases is rejected safely
--FILE--
<?php
function append_to_std_vector($container): void {
    $alias = $container->toStdVector(Type::Int);
    $alias[] = 3;
}

function main() {
    $vector = std::vector(Type::Int);
    $vector[] = 1;
    $vector[] = 2;

    foreach ($vector as $value) {
        try {
            append_to_std_vector($vector);
        } catch (Throwable $e) {
            echo "blocked\n";
        }
    }
    var_dump(count($vector));
}
?>
--EXPECT--
blocked
blocked
int(2)
