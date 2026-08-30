<?php

class SetterHookProperty
{
    #[Setter]
    public int $value {
        set(int $value) {
        }
    }
}
