<?php
class ImmutableMethodPropertyWrite
{
    private string $value = '';

    #[Immutable]
    public function read(): string
    {
        $this->value = 'changed';
        return $this->value;
    }
}
