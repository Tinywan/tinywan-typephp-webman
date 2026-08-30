<?php

/**
 * JSON-backed CRUD repository.  Writes use a temporary file and rename so a
 * crash cannot leave a partially written configuration.
 */
class TunnelRepository
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /** @return array<TunnelRule> */
    public function all(): array
    {
        $primaryError = null;
        if (is_file($this->file)) {
            try {
                return $this->readRules($this->file);
            } catch (Throwable $error) {
                $primaryError = $error;
            }
        }

        $backup = $this->backupFile();
        if (is_file($backup)) {
            try {
                $rules = $this->readRules($backup);
                $json = file_get_contents($backup);
                if ($json === false) {
                    throw new RuntimeException('无法读取备份配置：' . $backup);
                }
                $this->ensureDirectory();
                $temporary = $this->file . '.recover.tmp';
                $this->writeDurably($temporary, $json);
                $this->replaceFile($temporary, $this->file);
                return $rules;
            } catch (Throwable $backupError) {
                if ($primaryError !== null) {
                    throw new RuntimeException(
                        '主配置和备份配置均已损坏：'
                        . $primaryError->getMessage() . '；'
                        . $backupError->getMessage()
                    );
                }
                throw $backupError;
            }
        }

        if ($primaryError !== null) {
            throw $primaryError;
        }
        return [];
    }

    /** @return array<TunnelRule> */
    private function readRules(string $file): array
    {
        $json = file_get_contents($file);
        if ($json === false || trim($json) === '') {
            throw new RuntimeException('配置文件为空或无法读取：' . $file);
        }
        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            throw new RuntimeException('配置文件不是有效的 JSON：' . $file);
        }

        $rules = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $rules[] = new TunnelRule($row);
            }
        }
        return $rules;
    }

    public function find(string $id): ?TunnelRule
    {
        foreach ($this->all() as $rule) {
            if ($rule->id === $id) {
                return $rule;
            }
        }
        return null;
    }

    public function create(TunnelRule $rule): void
    {
        $rules = $this->all();
        foreach ($rules as $current) {
            if ($current->id === $rule->id) {
                throw new RuntimeException('规则 ID 已存在：' . $rule->id);
            }
        }
        $rules[] = $rule;
        $this->save($rules);
    }

    public function update(TunnelRule $rule): void
    {
        $rules = $this->all();
        $found = false;
        foreach ($rules as $index => $current) {
            if ($current->id === $rule->id) {
                $rules[$index] = $rule;
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('规则不存在：' . $rule->id);
        }
        $this->save($rules);
    }

    public function delete(string $id): void
    {
        $rules = [];
        $found = false;
        foreach ($this->all() as $rule) {
            if ($rule->id === $id) {
                $found = true;
            } else {
                $rules[] = $rule;
            }
        }
        if (!$found) {
            throw new RuntimeException('规则不存在：' . $id);
        }
        $this->save($rules);
    }

    /** @param array<TunnelRule> $rules */
    private function save(array $rules): void
    {
        $this->ensureDirectory();

        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = $rule->toArray();
        }
        $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('无法序列化隧道配置');
        }
        $json .= "\n";

        // Keep a valid fallback before replacing the primary file. After the
        // primary replacement succeeds, mirror the new version to the backup
        // as well. At every interruption point at least one complete copy
        // remains available.
        $backupJson = $json;
        if (is_file($this->file)) {
            try {
                $this->readRules($this->file);
                $current = file_get_contents($this->file);
                if ($current !== false) {
                    $backupJson = $current;
                }
            } catch (Throwable) {
                // Never copy a corrupt primary over the valid backup.
            }
        }

        $backupTemporary = $this->backupFile() . '.tmp';
        $this->writeDurably($backupTemporary, $backupJson);
        $this->replaceFile($backupTemporary, $this->backupFile());

        $temporary = $this->file . '.tmp';
        $this->writeDurably($temporary, $json);
        $this->replaceFile($temporary, $this->file);

        $this->writeDurably($backupTemporary, $json);
        $this->replaceFile($backupTemporary, $this->backupFile());
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建配置目录：' . $directory);
        }
    }

    private function backupFile(): string
    {
        return $this->file . '.bak';
    }

    private function writeDurably(string $file, string $content): void
    {
        $handle = fopen($file, 'wb');
        if ($handle === false) {
            throw new RuntimeException('无法打开配置文件：' . $file);
        }

        $length = strlen($content);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($handle, substr($content, $offset));
            if ($written === false || $written === 0) {
                fclose($handle);
                throw new RuntimeException('无法完整写入配置：' . $file);
            }
            $offset += $written;
        }
        if (!fflush($handle)) {
            fclose($handle);
            throw new RuntimeException('无法刷新配置：' . $file);
        }
        if (function_exists('fsync') && !fsync($handle)) {
            fclose($handle);
            throw new RuntimeException('无法同步配置到磁盘：' . $file);
        }
        fclose($handle);
        chmod($file, 0600);
    }

    private function replaceFile(string $source, string $destination): void
    {
        // PHP's Windows rename cannot consistently replace an existing file
        // across all supported runtimes. The durable backup written above
        // makes this fallback recoverable.
        if (PHP_OS_FAMILY === 'Windows' && is_file($destination) && !unlink($destination)) {
            throw new RuntimeException('无法替换已有配置：' . $destination);
        }
        if (!rename($source, $destination)) {
            throw new RuntimeException('无法替换配置：' . $destination);
        }
    }
}
