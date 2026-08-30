--TEST--
Traits - Basic functionality and method inheritance
--SKIPIF--
--FILE--
<?php
// Test basic trait usage
trait Greeting {
    public function sayHello() {
        return "Hello";
    }
    
    public function sayGoodbye() {
        return "Goodbye";
    }
}

class Person {
    use Greeting;
    
    private $name;
    
    public function __construct($name) {
        $this->name = $name;
    }
    
    public function getName() {
        return $this->name;
    }
}

// Test trait with abstract methods
trait Loggable {
    abstract public function getTableName();
    
    public function log($message) {
        return "[" . $this->getTableName() . "] " . $message;
    }
}

class User {
    use Loggable;
    
    public function getTableName() {
        return "users";
    }
}

// Test multiple traits
trait Timestamps {
    public function getCreatedAt() {
        return "2024-01-01 00:00:00";
    }
    
    public function getUpdatedAt() {
        return "2024-01-02 00:00:00";
    }
}

class Post {
    use Greeting, Timestamps;
    
    public function getTitle() {
        return "Test Post";
    }
}

function main() {
    // Test basic trait
    $person = new Person("John");
    var_dump($person->sayHello());
    var_dump($person->sayGoodbye());
    var_dump($person->getName());
    
    // Test trait with abstract method
    $user = new User();
    var_dump($user->log("User created"));
    
    // Test multiple traits
    $post = new Post();
    var_dump($post->sayHello());
    var_dump($post->getTitle());
    var_dump($post->getCreatedAt());
    var_dump($post->getUpdatedAt());
}
?>
--EXPECT--
string(5) "Hello"
string(7) "Goodbye"
string(4) "John"
string(20) "[users] User created"
string(5) "Hello"
string(9) "Test Post"
string(19) "2024-01-01 00:00:00"
string(19) "2024-01-02 00:00:00"