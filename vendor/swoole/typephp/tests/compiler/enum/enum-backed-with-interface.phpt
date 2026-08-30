--TEST--
Backed enum with interface and trait
--FILE--
<?php
interface Labeled {
    public function label(): string;
}

trait Description {
    public function describe(): string {
        return "Status: " . $this->value . " - " . $this->label();
    }
}

enum Status: int implements Labeled {
    use Description;

    case Active = 1;
    case Inactive = 0;
    case Pending = 2;

    public function label(): string {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Pending => 'Pending',
        };
    }

    public function isActive(): bool {
        return $this === self::Active;
    }
}

function main(): void {
    var_dump(Status::Active->value);
    var_dump(Status::Active->label());
    var_dump(Status::Active->describe());
    var_dump(Status::Active->isActive());
    var_dump(Status::Inactive->isActive());
    var_dump(Status::from(2));
    var_dump(Status::tryFrom(99));
}
?>
--EXPECT--
int(1)
string(6) "Active"
string(18) "Status: 1 - Active"
bool(true)
bool(false)
enum(Status::Pending)
NULL
