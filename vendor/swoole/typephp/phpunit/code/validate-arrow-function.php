<?php

$validate = fn (#[Validate(FILTER_VALIDATE_EMAIL)] string $value): string => $value;
