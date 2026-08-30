--TEST--
concat assignment to an inherited property preserves value and reference arguments
--FILE--
<?php

class QueryConnection
{
    protected $query = '';
}

class MongoConnection extends QueryConnection
{
    public function build(): string
    {
        $this->query .= '.sort(' . json_encode(['id' => 1]) . ')';
        $this->query .= '.skip(' . 2 . ')';
        $this->query .= ';';
        return $this->query;
    }
}

function main(): void
{
    $connection = new MongoConnection();
    var_dump($connection->build());
}
?>
--EXPECT--
string(24) ".sort({"id":1}).skip(2);"
