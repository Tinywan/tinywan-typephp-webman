<?php

function validateDuplicate(
    #[Validate(FILTER_VALIDATE_INT)]
    #[Validate(FILTER_VALIDATE_INT)]
    int $value,
): int {
    return $value;
}
