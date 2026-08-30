<?php

function emailFromUnion(
    #[Validate(FILTER_VALIDATE_EMAIL)] int|string $email,
): string|int {
    return $email;
}

function integerArray(
    #[Validate(FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY)] array $values,
): array {
    return $values;
}
