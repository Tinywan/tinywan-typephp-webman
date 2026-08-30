<?php

function validatedValue(
    #[Validate(FILTER_VALIDATE_EMAIL)]
    #[NotEmpty]
    #[NotNull]
    ?string $value,
): ?string {
    return $value;
}
