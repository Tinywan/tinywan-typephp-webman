<?php

function invalidFilter(
    #[Validate(FILTER_SANITIZE_EMAIL)] string $email,
): void {
}
