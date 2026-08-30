<?php

class AssignedValueNullCheck
{
    public function check(): bool
    {
        if (!is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            return true;
        }
        return false;
    }

    private function isFatal(int $type): bool
    {
        return $type > 0;
    }
}
