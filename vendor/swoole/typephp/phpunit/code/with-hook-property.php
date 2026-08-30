<?php

class WithHookProperty
{
    #[With]
    public int $value {
        get {
            return 1;
        }
    }
}
