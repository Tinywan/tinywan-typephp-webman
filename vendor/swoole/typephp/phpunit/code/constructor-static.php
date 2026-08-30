<?php

class InvalidStaticConstructor
{
    #[Constructor]
    private static int $value;
}
