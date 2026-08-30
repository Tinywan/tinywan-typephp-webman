<?php

class GeneratedMethodConflictParent
{
    protected function setName(string $name): void
    {
    }
}

class GeneratedMethodConflictChild extends GeneratedMethodConflictParent
{
    #[Setter]
    private string $name;
}
