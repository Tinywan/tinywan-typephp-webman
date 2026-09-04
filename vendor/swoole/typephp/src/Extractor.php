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
     * Extract function definitions.
     *
     * @param string $filename File path
     * @param array $prefixes List of function-name prefixes
     *
     * @return array List of functions
     */
    public function extractFunctions(string $filename, array $prefixes = ['php_']): array
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("文件不存在: {$filename}");
        }

        $this->info("分析文件: {$filename}");
        $this->info('函数前缀: ' . implode(', ', $prefixes));

        // Run ctags.
        $tags = $this->runCtags($filename);

        // Filter and parse functions.
        $functions = [];
        foreach ($tags as $tag) {
            if ($tag['kind'] !== 'function') {
                continue;
            }

            $funcName = $tag['name'] ?? '';

            // Check the prefix.
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

            // Parse the detailed function information.
            $funcInfo = $this->parseFunction($filename, $tag);
            if ($funcInfo) {
                $functions[] = $funcInfo;
            }
        }

        $this->info('找到 ' . count($functions) . ' 个函数');

        return $functions;
    }

    /**
     * Extract functions from multiple files in bulk.
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
     * Check whether ctags is available.
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
     * Run the ctags command.
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

        // Parse the JSON output.
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
     * Parse the detailed information of a single function.
     */
    private function parseFunction(string $filename, array $tag): ?array
    {
        $funcName = $tag['name'] ?? '';
        $lineNum  = $tag['line'] ?? 0;

        if (empty($funcName) || $lineNum < 1) {
            return null;
        }

        // Extract the complete function signature.
        $signature = $this->extractSignature($filename, $lineNum, $funcName);

        if (empty($signature)) {
            return null;
        }

        // Parse the return type.
        $returnType = $this->parseReturnType($signature, $funcName);

        // Parse the parameters.
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
     * Extract the complete function signature from the source file.
     */
    private function extractSignature(string $filename, int $lineNum, string $funcName): string
    {
        $lines = file($filename, FILE_IGNORE_NEW_LINES);

        if ($lines === false || $lineNum > count($lines)) {
            return '';
        }

        // Collect lines starting from the function declaration until a { or ; is reached.
        $signatureLines = [];
        $maxLines       = min($lineNum + 20, count($lines));

        for ($i = $lineNum - 1; $i < $maxLines; $i++) {
            $line             = $lines[$i];
            $signatureLines[] = $line;

            // Check whether the function body or the end of the declaration has been reached.
            if (strpos($line, '{') !== false || strpos($line, ';') !== false) {
                break;
            }
        }

        // Join and clean up.
        $signature = implode(' ', $signatureLines);

        // Remove everything after the { or ;.
        $signature = preg_replace('/[{;].*$/', '', $signature);

        // Collapse multiple whitespace characters.
        $signature = preg_replace('/\s+/', ' ', $signature);

        // Trim leading and trailing whitespace.
        return trim($signature);
    }

    /**
     * Parse the return type.
     */
    private function parseReturnType(string $signature, string $funcName): string
    {
        // Match: <return type> <function name>(
        $pattern = '/^(.+?)\s+' . preg_quote($funcName, '/') . '\s*\(/';

        if (preg_match($pattern, $signature, $matches)) {
            $returnType = trim($matches[1]);

            // Remove possible modifiers.
            $returnType = preg_replace('/\b(static|inline|extern|virtual|explicit)\b/', '', $returnType);
            $returnType = preg_replace('/\s+/', ' ', $returnType);
            $returnType = trim($returnType);

            return $returnType ?: 'void';
        }

        return 'unknown';
    }

    /**
     * Parse the parameter list.
     */
    private function parseParameters(string $signature, string $funcName): array
    {
        // Extract the parameters inside the parentheses.
        $pattern = '/' . preg_quote($funcName, '/') . '\s*\((.*?)\)/s';

        if (!preg_match($pattern, $signature, $matches)) {
            return [];
        }

        $paramsStr = trim($matches[1]);

        // Empty parameters or void.
        if (empty($paramsStr) || $paramsStr === 'void') {
            return [];
        }

        // Split the parameters (handling nested templates and parentheses).
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
     * Intelligently split parameters (handling nested templates and parentheses).
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
     * Parse a single parameter.
     */
    private function parseParameter(string $param): ?array
    {
        $param = trim($param);

        // Remove the default value.
        $param = preg_replace('/\s*=\s*.*$/', '', $param);

        // Try to match: <type> <name>.
        // Supports complex types such as: const char*, std::string&, int**, etc.
        if (preg_match('/^(.+?)\s+(\w+)\s*$/', $param, $matches)) {
            return [
                'type' => trim($matches[1]),
                'name' => trim($matches[2]),
            ];
        }

        // Only a type, no name.
        return [
            'type' => $param,
            'name' => '',
        ];
    }

    /**
     * Output an informational message.
     */
    private function info(string $message): void
    {
        fprintf(STDERR, "\033[0;32m%s\033[0m\n", $message);
    }

    /**
     * Output a warning.
     */
    private function warn(string $message): void
    {
        fprintf(STDERR, "\033[1;33m警告: %s\033[0m\n", $message);
    }

    /**
     * Output an error and exit.
     */
    private function error(string $message): void
    {
        fprintf(STDERR, "\033[0;31m错误: %s\033[0m\n", $message);
        exit(1);
    }

}
