<?php

#[Printer(['id', 'hidden'])]
class PrinterPrivateFields
{
    private int $id = 1;
    private string $hidden = 'secret';
}
