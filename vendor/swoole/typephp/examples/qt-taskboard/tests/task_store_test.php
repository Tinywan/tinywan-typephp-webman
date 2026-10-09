<?php

require dirname(__DIR__) . '/app/TaskStore.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'typephp-taskboard-test-' . uniqid() . '.sqlite';
$store = new TaskStore($file);
check(count($store->all()) === 5, 'first launch creates five sample tasks');

$id = $store->save([
    'title' => '验证任务模型',
    'description' => '先完成模型，再连接 Qt。',
    'priority' => 'high',
    'status' => 'todo',
]);
check($store->find($id)['status'] === 'todo', 'create task');
$store->advance($id);
check($store->find($id)['status'] === 'doing', 'advance to doing');
$store->advance($id);
check($store->find($id)['status'] === 'done', 'advance to done');

$reloaded = new TaskStore($file);
check($reloaded->find($id)['title'] === '验证任务模型', 'persist and reload Unicode');
$reloaded->delete($id);
check($reloaded->find($id) === null, 'delete task');

$failed = false;
try {
    $reloaded->save(['title' => '   ', 'status' => 'todo', 'priority' => 'normal']);
} catch (InvalidArgumentException) {
    $failed = true;
}
check($failed, 'reject empty title');

unset($store, $reloaded);
unlink($file);
echo "task-store-ok\n";
