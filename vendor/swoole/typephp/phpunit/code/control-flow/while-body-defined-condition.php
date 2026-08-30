<?php

function while_body_defined_condition(): void
{
    while (count($results) > 0) {
        $results = [1, 2, 3];
    }
}
