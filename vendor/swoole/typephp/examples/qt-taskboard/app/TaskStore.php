<?php

/** Task rules and SQLite persistence, implemented with PHP PDO_SQLITE. */
class TaskStore
{
    private PDO $db;

    public function __construct(string $file)
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建数据目录：' . $directory);
        }
        $firstLaunch = !is_file($file);
        $this->db = new PDO('sqlite:' . $file);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('PRAGMA busy_timeout = 2000');
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS tasks ('
            . 'id TEXT PRIMARY KEY, '
            . 'title TEXT NOT NULL, '
            . 'description TEXT NOT NULL, '
            . 'status TEXT NOT NULL, '
            . 'priority TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL'
            . ')'
        );
        if ($firstLaunch) {
            $this->seedExamples();
        }
    }

    public function all(): array
    {
        $query = $this->db->query('SELECT id, title, description, status, priority, created_at FROM tasks ORDER BY rowid');
        if ($query === false) {
            throw new RuntimeException('无法读取任务列表');
        }
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(string $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, title, description, status, priority, created_at FROM tasks WHERE id = :id'
        );
        if ($statement === false || !$statement->execute([':id' => $id])) {
            throw new RuntimeException('无法查询任务');
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function save(array $input): string
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('任务标题不能为空');
        }
        if (strlen($title) > 300) {
            throw new InvalidArgumentException('任务标题过长');
        }
        $status = (string) ($input['status'] ?? 'todo');
        if (!in_array($status, ['todo', 'doing', 'done'], true)) {
            throw new InvalidArgumentException('无效的任务状态');
        }
        $priority = (string) ($input['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high'], true)) {
            throw new InvalidArgumentException('无效的优先级');
        }

        $id = (string) ($input['id'] ?? '');
        $previous = $id === '' ? null : $this->find($id);
        if ($id !== '' && $previous === null) {
            throw new InvalidArgumentException('任务不存在：' . $id);
        }
        if ($id === '') {
            $id = str_replace('.', '', uniqid('task_', true));
        }

        $statement = $this->db->prepare(
            'INSERT INTO tasks (id, title, description, status, priority, created_at) '
            . 'VALUES (:id, :title, :description, :status, :priority, :created_at) '
            . 'ON CONFLICT(id) DO UPDATE SET '
            . 'title = excluded.title, description = excluded.description, '
            . 'status = excluded.status, priority = excluded.priority'
        );
        if ($statement === false || !$statement->execute([
            ':id' => $id,
            ':title' => $title,
            ':description' => trim((string) ($input['description'] ?? '')),
            ':status' => $status,
            ':priority' => $priority,
            ':created_at' => $previous['created_at'] ?? date('Y-m-d'),
        ])) {
            throw new RuntimeException('无法保存任务');
        }
        return $id;
    }

    public function advance(string $id): void
    {
        $task = $this->find($id);
        if ($task === null) {
            throw new InvalidArgumentException('任务不存在：' . $id);
        }
        $task['status'] = match ($task['status']) {
            'todo' => 'doing',
            'doing' => 'done',
            default => 'todo',
        };
        $this->save($task);
    }

    public function delete(string $id): void
    {
        if ($this->find($id) === null) {
            throw new InvalidArgumentException('任务不存在：' . $id);
        }
        $statement = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        if ($statement === false || !$statement->execute([':id' => $id])) {
            throw new RuntimeException('无法删除任务');
        }
    }

    private function seedExamples(): void
    {
        $examples = [
            ['title' => '设计桌面应用首页', 'description' => '梳理导航、任务列表与详情面板，让常用操作触手可及。', 'status' => 'done', 'priority' => 'high'],
            ['title' => '完成 TypePHP 任务模型', 'description' => '实现任务校验、状态流转、搜索筛选和 PDO_SQLITE 持久化。', 'status' => 'doing', 'priority' => 'high'],
            ['title' => '连接 Qt Widgets 界面', 'description' => '用原生控件展示任务，并把点击事件送回 PHP。', 'status' => 'doing', 'priority' => 'normal'],
            ['title' => '验证桌面应用打包', 'description' => '检查 Qt 插件和 PHP / PHPX 运行库是否齐全。', 'status' => 'todo', 'priority' => 'normal'],
            ['title' => '发布第一篇开发文章', 'description' => '加入真实运行截图和完整的构建步骤。', 'status' => 'todo', 'priority' => 'low'],
        ];
        $this->db->beginTransaction();
        try {
            foreach ($examples as $task) {
                $this->save($task);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }
}
