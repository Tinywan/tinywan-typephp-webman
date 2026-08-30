<?php

namespace CompileTimeAttributes;

use \Arrayable;
use \Getter;
use \Validate;
use \NotNull;
use \NotEmpty;
use \Printer;
use \Setter;
use \With;

class PrintableBase
{
    public int $baseId = 1;

    protected string $ignored = 'hidden';
}

#[Printer(fields: ['id', 'name'])]
#[Arrayable(['baseId', 'name'])]
class User extends PrintableBase
{
    public int $id = 2;

    public string $name = '张三';

    #[Getter, Setter, With]
    private string $nickname = 'typephp';

    public function rename(#[NotNull] string $name): void
    {
        $this->name = $name;
    }
}

class CustomPrinterBase
{
    public function __toString(): string
    {
        return 'custom';
    }
}

class CustomArrayableBase
{
    public function toArray(): array
    {
        return ['custom' => true];
    }
}

#[Arrayable]
class CustomArrayableChild extends CustomArrayableBase
{
    public int $value = 1;
}

#[Arrayable]
class DefaultArrayable extends PrintableBase
{
    public string $name = 'default';
    protected string $hidden = 'hidden';
    public static int $shared = 1;
}

#[Printer]
class CustomPrinterChild extends CustomPrinterBase
{
    public int $value = 1;
}

#[Printer]
class LatePrinterChild extends LatePrinterBase
{
    public int $value = 1;
}

class LatePrinterBase
{
    public function __toString(): string
    {
        return 'late';
    }
}

class PromotedProperties
{
    public function __construct(
        #[Getter, Setter, With]
        private int $value,
    ) {
    }
}

function requireValue(#[NotNull] int $value): int
{
    return $value;
}

function requireEmail(
    #[NotEmpty]
    #[Validate(FILTER_VALIDATE_EMAIL, message: 'Invalid email')]
    string $email,
): string {
    return $email;
}

function requirePort(
    #[Validate(
        FILTER_VALIDATE_INT,
        options: ['options' => ['min_range' => 1, 'max_range' => 65535]],
    )]
    int $port,
): int {
    return $port;
}

function requireBoolean(#[Validate(FILTER_VALIDATE_BOOLEAN)] bool $value): bool
{
    return $value;
}

function main(): void
{
    $requireName = function (#[NotNull] string $name): string {
        return $name;
    };
    $requireNonEmptyName = function (#[NotEmpty] string $name): string {
        return $name;
    };
    $requireValidEmail = function (
        #[Validate(FILTER_VALIDATE_EMAIL)] string $email,
    ): string {
        return $email;
    };
    $user = new User();
    $user->setNickname('php');
    $copy = $user->withNickname('cpp');
    echo $user->getNickname();
    echo $copy->getNickname();
    echo $user;
    $userData = $user->toArray();
    echo $userData['baseId'];
    echo $userData['name'];
    $defaultData = (new DefaultArrayable())->toArray();
    echo $defaultData['baseId'];
    echo $defaultData['name'];
    echo (new CustomArrayableChild())->toArray()['custom'];
    echo new CustomPrinterChild();
    echo new LatePrinterChild();
    echo requireValue(1);
    echo $requireName('typephp');
    echo $requireNonEmptyName('typephp');
    echo $requireValidEmail('typephp@example.com');
    echo requireEmail('user@example.com');
    echo requirePort(9501);
    echo requireBoolean(false);
    echo $requireName('typephp');

    $promoted = new PromotedProperties(1);
    $promoted->setValue(2);
    echo $promoted->withValue(3)->getValue();
}
