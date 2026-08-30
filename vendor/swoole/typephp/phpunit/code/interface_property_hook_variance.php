<?php

class Animal
{
}

class Dog extends Animal
{
}

interface ReadsAnimal
{
    public Animal $value { get; }
}

final class ReadsDog implements ReadsAnimal
{
    public Dog $value;
}

interface WritesDog
{
    public Dog $value { set; }
}

final class WritesAnimal implements WritesDog
{
    public Animal $value;
}
