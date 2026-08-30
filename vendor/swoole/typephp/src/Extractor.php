<?php
/**
 * This file is part of TypePHP.
 *
 * @link     https://www.swoole.com/
 * @contact  service@swoole.com
 */

namespace TypePhp;

class Extractor
{
    private string $ctagsPath = 'ctags';

    public function __construct()
    {
        $this->checkCtags();
    }

    /**
     * 提取函数定义.
     *
     * @param string $filename 文件路径
     * @param array $prefixes 函数名前缀列表
     *
     * @return array 函数列表
     */
    public function extractFunctions(string $filename, array $prefixes = ['php_']): array
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("文件不存在: {$filename}");
        }

        $this->info("分析文件: {$filename}");
        $this->info('函数前缀: ' . implode(', ', $prefixes));

        // 运行 ctags
        $tags = $this->runCtags($filename);

        // 过滤和解析函数
        $functions = [];
        foreach ($tags as $tag) {
            if ($tag['kind'] !== 'function') {
                continue;
            }

            $funcName = $tag['name'] ?? '';

            // 检查前缀
            $matched = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($funcName, $prefix)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            // 解析函数详细信息
            $funcInfo = $this->parseFunction($filename, $tag);
            if ($funcInfo) {
                $functions[] = $funcInfo;
            }
        }

        $this->info('找到 ' . count($functions) . ' 个函数');

        return $functions;
    }

    /**
     * 批量提取多个文件.
     */
    public function extractFromFiles(array $files, array $prefixes = ['php_']): array
    {
        $allFunctions = [];

        foreach ($files as $file) {
            try {
                $functions    = $this->extractFunctions($file, $prefixes);
                $allFunctions = array_merge($allFunctions, $functions);
            } catch (\Throwable $e) {
                $this->error("处理文件 {$file} 失败: " . $e->getMessage());
            }
        }

        return $allFunctions;
    }

    /**
     * 检查 ctags 是否可用.
     */
    private function checkCtags(): void
    {
        $output = shell_exec("{$this->ctagsPath} --version 2>&1");

        if ($output === null) {
            $this->error("未找到 ctags 命令\n安装: sudo apt install universal-ctags");
        }

        if (stripos($output, 'Universal Ctags') === false) {
            $this->warn('建议使用 Universal Ctags 以获得更好的支持');
        }
    }

    /**
     * 运行 ctags 命令.
     */
    private function runCtags(string $filename): array
    {
        $cmd = sprintf(
            '%s --output-format=json --fields=+nKSzZt --kinds-c++=f --extras=+q -f - %s 2>&1',
            escapeshellcmd($this->ctagsPath),
            escapeshellarg($filename)
        );

        $output = shell_exec($cmd);

        if ($output === null) {
            throw new \RuntimeException('ctags 执行失败');
        }

        // 解析 JSON 输出
        $tags  = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $tag = json_decode($line, true);
            if ($tag === null) {
                continue;
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * 解析单个函数的详细信息.
     */
    private function parseFunction(string $filename, array $tag): ?array
    {
        $funcName = $tag['name'] ?? '';
        $lineNum  = $tag['line'] ?? 0;

        if (empty($funcName) || $lineNum < 1) {
            return null;
        }

        // 提取完整的函数签名
        $signature = $this->extractSignature($filename, $lineNum, $funcName);

        if (empty($signature)) {
            return null;
        }

        // 解析返回类型
        $returnType = $this->parseReturnType($signature, $funcName);

        // 解析参数
        $parameters = $this->parseParameters($signature, $funcName);

        return [
            'name'       => $funcName,
            'returnType' => $returnType,
            'signature'  => $signature,
            'parameters' => $parameters,
            'location'   => [
                'file' => $filename,
                'line' => $lineNum,
            ],
            'scope'     => $tag['scope'] ?? null,
            'scopeKind' => $tag['scopeKind'] ?? null,
        ];
    }

    /**
     * 从源文件中提取完整的函数签名.
     */
    private function extractSignature(string $filename, int $lineNum, string $funcName): string
    {
        $lines = file($filename, FILE_IGNORE_NEW_LINES);

        if ($lines === false || $lineNum > count($lines)) {
            return '';
        }

        // 从函数声明行开始收集，直到遇到 { 或 ;
        $signatureLines = [];
        $maxLines       = min($lineNum + 20, count($lines));

        for ($i = $lineNum - 1; $i < $maxLines; $i++) {
            $line             = $lines[$i];
            $signatureLines[] = $line;

            // 检查是否到达函数体或声明结束
            if (strpos($line, '{') !== false || strpos($line, ';') !== false) {
                break;
            }
        }

        // 合并并清理
        $signature = implode(' ', $signatureLines);

        // 移除 { 或 ; 之后的内容
        $signature = preg_replace('/[{;].*$/', '', $signature);

        // 合并多个空白字符
        $signature = preg_replace('/\s+/', ' ', $signature);

        // 清理首尾空白
        return trim($signature);
    }

    /**
     * 解析返回类型.
     */
    private function parseReturnType(string $signature, string $funcName): string
    {
        // 匹配: <返回类型> <函数名>(
        $pattern = '/^(.+?)\s+' . preg_quote($funcName, '/') . '\s*\(/';

        if (preg_match($pattern, $signature, $matches)) {
            $returnType = trim($matches[1]);

            // 移除可能的修饰符
            $returnType = preg_replace('/\b(static|inline|extern|virtual|explicit)\b/', '', $returnType);
            $returnType = preg_replace('/\s+/', ' ', $returnType);
            $returnType = trim($returnType);

            return $returnType ?: 'void';
        }

        return 'unknown';
    }

    /**
     * 解析参数列表.
     */
    private function parseParameters(string $signature, string $funcName): array
    {
        // 提取括号内的参数
        $pattern = '/' . preg_quote($funcName, '/') . '\s*\((.*?)\)/s';

        if (!preg_match($pattern, $signature, $matches)) {
            return [];
        }

        $paramsStr = trim($matches[1]);

        // 空参数或 void
        if (empty($paramsStr) || $paramsStr === 'void') {
            return [];
        }

        // 分割参数（处理嵌套的模板和括号）
        $params = $this->splitParameters($paramsStr);

        $parameters = [];
        foreach ($params as $param) {
            $param = trim($param);

            if (empty($param)) {
                continue;
            }

            $paramInfo = $this->parseParameter($param);
            if ($paramInfo) {
                $parameters[] = $paramInfo;
            }
        }

        return $parameters;
    }

    /**
     * 智能分割参数（处理嵌套的模板和括号）.
     */
    private function splitParameters(string $paramsStr): array
    {
        $params  = [];
        $current = '';
        $depth   = 0;
        $length  = strlen($paramsStr);

        for ($i = 0; $i < $length; $i++) {
            $char = $paramsStr[$i];

            if ($char === '<' || $char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
            } elseif ($char === '>' || $char === ')' || $char === ']') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $params[] = $current;
                $current  = '';
            } else {
                $current .= $char;
            }
        }

        if (!empty($current)) {
            $params[] = $current;
        }

        return $params;
    }

    /**
     * 解析单个参数.
     */
    private function parseParameter(string $param): ?array
    {
        $param = trim($param);

        // 移除默认值
        $param = preg_replace('/\s*=\s*.*$/', '', $param);

        // 尝试匹配: <类型> <名称>
        // 支持复杂类型如: const char*, std::string&, int**, etc.
        if (preg_match('/^(.+?)\s+(\w+)\s*$/', $param, $matches)) {
            return [
                'type' => trim($matches[1]),
                'name' => trim($matches[2]),
            ];
        }

        // 只有类型，没有名称
        return [
            'type' => $param,
            'name' => '',
        ];
    }

    /**
     * 输出信息.
     */
    private function info(string $message): void
    {
        fprintf(STDERR, "\033[0;32m%s\033[0m\n", $message);
    }

    /**
     * 输出警告.
     */
    private function warn(string $message): void
    {
        fprintf(STDERR, "\033[1;33m警告: %s\033[0m\n", $message);
    }

    /**
     * 输出错误并退出.
     */
    private function error(string $message): void
    {
        fprintf(STDERR, "\033[0;31m错误: %s\033[0m\n", $message);
        exit(1);
    }

}
