<?php

namespace App;

function importVariables(array $values): void
{
    \extract($values);
}
