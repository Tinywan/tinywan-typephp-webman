<?php

class CompositeTrueBox
{
    public true|null $value = null;

    public function fail(): void
    {
        $this->value = false;
    }
}
