<?php

class GetterHookProperty
{
    #[Getter]
    public int $value {
        get {
            return 1;
        }
    }
}
