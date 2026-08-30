<?php

/**
 * Application controller.  CRUD decisions, validation, persistence, SSH
 * argument generation and runtime state all live in TypePHP.
 */
class TunnelApplication
{
    private TunnelRepository $repository;
    private mixed $window;
    private array $states = [];
    private SshOutputParser $sshOutputParser;

    public function __construct(TunnelRepository $repository)
    {
        $this->repository = $repository;
        $this->sshOutputParser = new SshOutputParser();
        $this->window = qt_tunnel_create('TypePHP SSH Tunnel Manager');
    }

    public function run(): int
    {
        $this->refresh();
        foreach ($this->repository->all() as $rule) {
            // Every persisted rule is desired state. A newly started manager
            // owns no SSH child process yet, so all rules must be reconciled.
            $this->start($rule->id);
        }

        while (qt_tunnel_is_open($this->window)) {
            qt_tunnel_process_events($this->window);
            while (true) {
                $event = qt_tunnel_poll_event($this->window);
                if ($event === []) {
                    break;
                }
                $this->handleEvent($event);
            }
        }

        foreach (array_keys($this->states) as $id) {
            if (($this->states[$id] ?? 'stopped') !== 'stopped') {
                qt_tunnel_stop_process($this->window, (string) $id);
            }
        }
        qt_tunnel_destroy($this->window);
        return 0;
    }

    private function handleEvent(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $id = (string) ($event['id'] ?? '');

        try {
            if ($type === 'create') {
                $this->repository->create(new TunnelRule((array) $event['payload']));
                $this->refresh();
            } elseif ($type === 'update') {
                if (($this->states[$id] ?? 'stopped') !== 'stopped') {
                    throw new RuntimeException('请先停止隧道再编辑');
                }
                $payload = (array) $event['payload'];
                $payload['id'] = $id;
                $this->repository->update(new TunnelRule($payload));
                $this->refresh();
            } elseif ($type === 'delete') {
                $this->stop($id);
                $this->repository->delete($id);
                unset($this->states[$id]);
                $this->refresh();
            } elseif ($type === 'start') {
                $this->start($id);
            } elseif ($type === 'stop') {
                $this->stop($id);
            } elseif ($type === 'process_started') {
                $this->states[$id] = 'running';
                $this->log($id, 'SSH 隧道已建立');
                $this->refresh();
            } elseif ($type === 'process_stopped') {
                $this->states[$id] = 'stopped';
                $this->sshOutputParser->clear($id);
                $this->log($id, 'SSH 进程已停止');
                $this->refresh();
            } elseif ($type === 'process_error') {
                $this->states[$id] = 'error';
                $this->sshOutputParser->clear($id);
                $message = (string) ($event['message'] ?? 'SSH 进程错误');
                $this->log($id, $message);
                qt_tunnel_show_error($this->window, $message);
                $this->refresh();
            } elseif ($type === 'process_output') {
                $rule = $this->repository->find($id);
                $message = $this->sshOutputParser->parse(
                    $id,
                    (string) ($event['message'] ?? ''),
                    $rule !== null && $rule->type === TunnelRule::TYPE_SOCKS5,
                    $rule !== null && $rule->debug
                );
                if ($message !== null) {
                    $this->log($id, $message);
                }
            }
        } catch (Throwable $error) {
            qt_tunnel_show_error($this->window, $error->getMessage());
        }
    }

    private function start(string $id): void
    {
        if (($this->states[$id] ?? 'stopped') === 'running'
            || ($this->states[$id] ?? 'stopped') === 'starting') {
            return;
        }
        $rule = $this->repository->find($id);
        if ($rule === null) {
            throw new RuntimeException('规则不存在：' . $id);
        }

        $this->states[$id] = 'starting';
        $this->refresh();
        $this->log($id, '正在连接 ' . $rule->sshUser . '@' . $rule->sshHost);
        if (!qt_tunnel_start_process($this->window, $id, 'ssh', $rule->sshArguments())) {
            $this->states[$id] = 'error';
            $this->refresh();
            throw new RuntimeException('无法启动 ssh，请确认 OpenSSH 客户端已安装并位于 PATH');
        }
    }

    private function stop(string $id): void
    {
        $state = (string) ($this->states[$id] ?? 'stopped');
        if ($state === 'stopped') {
            return;
        }
        $this->states[$id] = 'stopping';
        qt_tunnel_stop_process($this->window, $id);
        $this->refresh();
    }

    private function refresh(): void
    {
        $rows = [];
        foreach ($this->repository->all() as $rule) {
            $row = $rule->toArray();
            $row['type_label'] = $rule->typeLabel();
            $row['local_address_label'] = $rule->localAddressLabel();
            $row['remote_address_label'] = $rule->remoteAddressLabel();
            $row['server_label'] = $rule->sshUser . '@' . $rule->sshHost . ':' . $rule->sshPort;
            $row['status'] = (string) ($this->states[$rule->id] ?? 'stopped');
            $rows[] = $row;
        }
        qt_tunnel_set_rules($this->window, $rows);
    }

    private function log(string $id, string $message): void
    {
        if ($message !== '') {
            qt_tunnel_append_log($this->window, $id, $message);
        }
    }
}
