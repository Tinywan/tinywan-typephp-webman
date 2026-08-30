--TEST--
Readonly Classes (PHP 8.2+)
--FILE--
<?php
enum T { case A; case M; }

readonly class Q {
    public function __construct(
        public string $t,
        public array $o = [],
        public array $a = [1 => 'yes', 'next' => 'no', 2 => 'maybe'],
        public T $type = T::A,
    ) {}
}

function main(): void {
    $questions = [
        new Q("1+1=2？", ['yes', 'no'], ['yes']),
        new Q("哪些是数字？", ['1', '2', 'a', 'b'], ['1', '2'], T::M),
    ];

    foreach ($questions as $i => $q) {
        $num = $i + 1;
        $typeText = $q->type === T::A ? '单选' : '多选';

        echo "第{$num}题 [{$typeText}] {$q->t}\n";
        echo "选项: " . implode(', ', $q->o) . "\n";
        echo "答案: " . implode(', ', $q->a) . "\n";
    }
}
?>
--EXPECT--
第1题 [单选] 1+1=2？
选项: yes, no
答案: yes
第2题 [多选] 哪些是数字？
选项: 1, 2, a, b
答案: 1, 2
