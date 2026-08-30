<?php

class PrinterParentPrivateFieldBase
{
    private string $secret = 'hidden';
}

#[Printer(fields: ['secret'])]
class PrinterParentPrivateFieldChild extends PrinterParentPrivateFieldBase
{
}
