<?php

/** Controller: filtering, counts, selection and every mutation stay in PHP. */
class TaskBoardApplication
{
    private TaskStore $store;
    private mixed $window;
    private string $filter = 'all';
    private string $search = '';
    private string $selectedId = '';

    public function __construct(TaskStore $store)
    {
        $this->store = $store;
        $this->window = qt_board_create('TypePHP Taskboard');
    }

    public function run(): int
    {
        $this->refresh();
        $screenshot = getenv('TYPEPHP_TASKBOARD_SCREENSHOT');
        if (is_string($screenshot) && $screenshot !== '') {
            if (!qt_board_snapshot($this->window, $screenshot)) {
                throw new RuntimeException('无法保存界面截图：' . $screenshot);
            }
            qt_board_destroy($this->window);
            return 0;
        }
        while (qt_board_is_open($this->window)) {
            qt_board_process_events($this->window);
            while (true) {
                $event = qt_board_poll_event($this->window);
                if ($event === []) {
                    break;
                }
                try {
                    $this->handle($event);
                } catch (Throwable $error) {
                    qt_board_show_error($this->window, $error->getMessage());
                }
            }
        }
        qt_board_destroy($this->window);
        return 0;
    }

    private function handle(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $id = (string) ($event['id'] ?? '');
        if ($type === 'filter') {
            $filter = (string) ($event['value'] ?? 'all');
            $this->filter = in_array($filter, ['all', 'todo', 'doing', 'done'], true) ? $filter : 'all';
            $this->selectedId = '';
        } elseif ($type === 'search') {
            $this->search = trim((string) ($event['value'] ?? ''));
            $this->selectedId = '';
        } elseif ($type === 'select') {
            $this->selectedId = $id;
        } elseif ($type === 'save') {
            $this->selectedId = $this->store->save((array) ($event['payload'] ?? []));
        } elseif ($type === 'advance') {
            $this->store->advance($id);
            $this->selectedId = $id;
        } elseif ($type === 'delete') {
            $this->store->delete($id);
            $this->selectedId = '';
        }
        $this->refresh();
    }

    private function refresh(): void
    {
        $rows = [];
        $total = 0;
        $doing = 0;
        $done = 0;
        foreach ($this->store->all() as $task) {
            $total++;
            if ($task['status'] === 'doing') {
                $doing++;
            } elseif ($task['status'] === 'done') {
                $done++;
            }
            if ($this->filter !== 'all' && $task['status'] !== $this->filter) {
                continue;
            }
            if ($this->search !== ''
                && stripos($task['title'], $this->search) === false
                && stripos($task['description'], $this->search) === false) {
                continue;
            }
            $task['status_label'] = match ($task['status']) {
                'todo' => '待处理',
                'doing' => '进行中',
                default => '已完成',
            };
            $task['priority_label'] = match ($task['priority']) {
                'high' => '高优先级',
                'low' => '低优先级',
                default => '普通',
            };
            $rows[] = $task;
        }
        qt_board_set_view($this->window, $rows, [
            'total' => $total,
            'doing' => $doing,
            'done' => $done,
            'visible' => count($rows),
        ], $this->selectedId);
    }
}
