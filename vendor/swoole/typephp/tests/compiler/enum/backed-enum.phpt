--TEST--
Backed Enums (PHP 8.1+)
--FILE--
<?php

// Test basic backed enum with int values
enum Status: int {
    case Pending = 0;
    case Active = 1;
    case Suspended = 2;
    case Closed = 3;
    
    public function label(): string {
        return match($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Closed => 'Closed',
        };
    }
}

// Test backed enum with string values
enum Color: string {
    case Red = 'red';
    case Green = 'green';
    case Blue = 'blue';
    
    public function rgb(): array {
        return match($this) {
            self::Red => [255, 0, 0],
            self::Green => [0, 255, 0],
            self::Blue => [0, 0, 255],
        };
    }
}

// Test enum in switch
function processStatus(Status $status): string {
    return match($status) {
        Status::Pending => 'Task is pending',
        Status::Active => 'Task is active',
        Status::Suspended => 'Task is suspended',
        Status::Closed => 'Task is closed',
    };
}

function main() {
    var_dump(Status::Pending->value);
    var_dump(Status::Active->label());
    var_dump(Status::from(2)->name);

    var_dump(Color::Red->value);
    var_dump(Color::Green->rgb());

    var_dump(processStatus(Status::Active));

    // Test enum comparison
    var_dump(Status::Active === Status::Active);
    var_dump(Status::Pending !== Status::Active);

    // Test enum array usage
    $statuses = [Status::Pending, Status::Active, Status::Closed];
    foreach ($statuses as $status) {
        var_dump($status->label());
    }
}
?>
--EXPECT--
int(0)
string(6) "Active"
string(9) "Suspended"
string(3) "red"
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(255)
  [2]=>
  int(0)
}
string(14) "Task is active"
bool(true)
bool(true)
string(7) "Pending"
string(6) "Active"
string(6) "Closed"
