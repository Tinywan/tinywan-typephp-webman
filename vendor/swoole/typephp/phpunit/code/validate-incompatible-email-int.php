<?php

function invalidEmailType(
    #[Validate(FILTER_VALIDATE_EMAIL)] int $email,
): void {
}
