<?php

use TypePhp\CompilerTest;

/**
 * 从 count-globals.php 拆解的测试用例：
 *  - 优化器路径中未定义变量检测
 *  - $GLOBALS 数组访问形式正常编译
 */
class CountGlobalsTest extends \BaseTest
{
    public function testOptimizerUndefinedVar()
    {
        // in_array($key, $array) 走优化器路径 (FuncCallOptimizer)
        // 优化器检测到 $key 未定义后应回退到传统路径并给出明确错误
        $this->exec(
            'Undefined variable `$key`',
            'optimizer-undefined-var.php'
        );
    }

    public function testGlobalsArrayAccessAllowed()
    {
        // $GLOBALS['key'] 数组访问形式应正常通过编译
        $testFile = __DIR__ . '/../code/globals-array-access.php';
        $compiler = CompilerTest::create(TYPEPHP_ROOT_PATH);
        $compiler->addFiles([$testFile]);
        $compiler->prepareFile($testFile);
        $compiler->convertFile($testFile);

        $this->assertTrue(true); // 无异常即通过
    }
}
