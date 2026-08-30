<?php

class InvalidStaticGetter
{
    #[Getter]
    public static int $value = 1;
}
