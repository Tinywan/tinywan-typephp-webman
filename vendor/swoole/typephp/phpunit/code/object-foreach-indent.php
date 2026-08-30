<?php

function visitObjects(object $first, object $second): void
{
    foreach ($first as $value) {
        echo $value;
    }
    foreach ($second as $value) {
        echo $value;
    }
}
