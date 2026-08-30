<?php
function createUser(
    string $name,
    int    $age,
    string $city = 'Beijing',
    bool   $vip = false
): array
{
    return ['name' => $name, 'age' => $age, 'city' => $city, 'vip' => $vip];
}
