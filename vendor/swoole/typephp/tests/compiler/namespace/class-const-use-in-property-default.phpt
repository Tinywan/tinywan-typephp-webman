--TEST--
class const from imported global class in property default
--FILE--
<?php
namespace fizz\orm\db {
    use PDO;

    class PDOConnection
    {
        public int $fetchMode = PDO::FETCH_ASSOC;
        public array $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }
}

namespace {
    function main()
    {
        $conn = new fizz\orm\db\PDOConnection();
        var_dump($conn->fetchMode === \PDO::FETCH_ASSOC);
        var_dump($conn->options === [\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
    }
}
?>
--EXPECT--
bool(true)
bool(true)
