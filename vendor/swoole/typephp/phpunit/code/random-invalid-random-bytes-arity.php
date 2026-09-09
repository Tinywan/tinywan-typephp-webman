<?php

function invalidRandomBytesArity(): string
{
    return random_bytes(1, 2);
}
