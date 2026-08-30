<?php


class EightQueensOptimized
{
    private $solutions = [];
    private $n = 8;
    private $cols = [];      // 记录每行皇后的列位置

    public function __construct($n = 8)
    {
        $this->n = $n;
        $this->cols = array_fill(0, $n, -1);
    }

    /**
     * 检查在 (row, col) 位置放置皇后是否安全
     */
    private function isSafe($row, $col)
    {
        for ($i = 0; $i < $row; $i++) {
            // 检查列冲突和对角线冲突
            if ($this->cols[$i] == $col ||
                abs($this->cols[$i] - $col) == abs($i - $row)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 回溯求解
     */
    private function solve($row)
    {
        if ($row == $this->n) {
            $this->solutions[] = array_slice($this->cols, 0);
            return;
        }

        for ($col = 0; $col < $this->n; $col++) {
            if ($this->isSafe($row, $col)) {
                $this->cols[$row] = $col;
                $this->solve($row + 1);
                $this->cols[$row] = -1;
            }
        }
    }

    /**
     * 获取所有解决方案
     */
    public function getSolutions()
    {
        $this->solutions = [];
        $this->solve(0);
        return $this->solutions;
    }

    /**
     * 打印解决方案
     */
    public function printSolution($solution)
    {
        echo str_repeat('-', $this->n * 4 + 1) . "\n";
        for ($row = 0; $row < $this->n; $row++) {
            echo '| ';
            for ($col = 0; $col < $this->n; $col++) {
                echo ($solution[$row] == $col ? 'Q' : '.') . ' | ';
            }
            echo "\n" . str_repeat('-', $this->n * 4 + 1) . "\n";
        }
    }

    /**
     * 打印所有解决方案
     */
    public function printAllSolutions()
    {
        $solutions = $this->getSolutions();
        echo "找到 " . count($solutions) . " 个解决方案\n\n";

        foreach ($solutions as $index => $solution) {
            echo "解决方案 #" . ($index + 1) . ": [" .
                implode(', ', $solution) . "]\n";
            $this->printSolution($solution);
            echo "\n";
        }
    }

    /**
     * 只统计解的数量（不保存所有解）
     */
    public function countSolutions()
    {
        return count($this->getSolutions());
    }
}

function main()
{
    // 使用示例
    echo "=== 8 皇后问题 ===\n\n";
    $queens = new EightQueensOptimized(8);

    // 只显示前3个解决方案
    $solutions = $queens->getSolutions();
    echo "总共找到 " . count($solutions) . " 个解决方案\n\n";

    for ($i = 0; $i < min(3, count($solutions)); $i++) {
        echo "解决方案 #" . ($i + 1) . ":\n";
        $queens->printSolution($solutions[$i]);
        echo "\n";
    }

    // 测试不同规模
    $begin = microtime(true);
    echo "\n=== 不同规模的皇后问题 ===\n";
    for ($n = 4; $n <= 10; $n++) {
        $q = new EightQueensOptimized($n);
        $count = $q->countSolutions();
        echo "{$n} 皇后问题有 {$count} 个解\n";
    }
    echo "耗时: " . (microtime(true) - $begin) . " 秒\n";
}
