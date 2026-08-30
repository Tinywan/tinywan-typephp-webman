--TEST--
Asymmetric property set visibility works for static and dynamic writes
--FILE--
<?php

class BaseRecord
{
    public private(set) string $name = 'default';
    public protected(set) int $score = 0;

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function renameDynamically(mixed $target, string $name): void
    {
        $target->name = $name;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function setRelatedChildScore(mixed $target, int $score): void
    {
        $target->childScore = $score;
    }
}

class ChildRecord extends BaseRecord
{
    public protected(set) int $childScore = 0;

    public function setOwnScore(int $score): void
    {
        $this->score = $score;
    }

    public function setRelatedScore(mixed $target, int $score): void
    {
        $target->score = $score;
    }
}

function tryExternalWrites(mixed $record): void
{
    try {
        $record->name = 'outside';
    } catch (Error $error) {
        echo "private blocked\n";
    }
    try {
        $record->score = 99;
    } catch (Error $error) {
        echo "protected blocked\n";
    }
}

function main(): void
{
    $record = new ChildRecord();
    $record->rename('inside');
    $record->renameDynamically($record, 'dynamic inside');
    $record->setScore(10);
    $record->setOwnScore(15);
    $record->setRelatedScore($record, 20);
    $record->setRelatedChildScore($record, 30);
    var_dump($record->name, $record->score, $record->childScore);
    tryExternalWrites($record);
    var_dump($record->name, $record->score, $record->childScore);
}
?>
--EXPECT--
string(14) "dynamic inside"
int(20)
int(30)
private blocked
protected blocked
string(14) "dynamic inside"
int(20)
int(30)
