# TypePHP SSH Tunnel Manager（Qt）

这是一个 TypePHP 桌面应用示例。Qt/C++ 只承担窗口、控件、UI 事件队列和
`QProcess` 桥接；规则校验、CRUD、JSON 持久化、SSH 参数生成、自动启动和运行
状态均由 TypePHP 实现。

## 功能

- 本地转发：把服务器可访问的目标端口映射到本地监听端口（OpenSSH `-L`）。
- 远程转发：把本地可访问的目标端口映射到服务器监听端口（OpenSSH `-R`）。
- SOCKS5：通过 SSH 服务器建立本地 SOCKS5 代理（OpenSSH `-D`）。
- 新建、查看、编辑和删除规则。
- 单条隧道启动、停止、状态展示和 SSH 输出日志。
- 支持中文输入法组合事件（Linux IBus/Fcitx、Windows TSF/IMM）。
- 表格列宽可拖动调整，刷新规则时不会重置列宽。
- 映射端点分别显示为“本机地址”和“远程地址”；远程转发会按实际方向交换显示。
- 状态列使用状态灯：运行中为绿色，已停止或错误为红色，过渡状态为橙色。
- 日志按隧道独立保存在内存中，选择表格行时只显示该隧道最近 500 条日志；当前日志增量追加，切换隧道时才进行一次有限量重绘。
- 可清理当前选中隧道的日志，不影响其他隧道或正在运行的 SSH 进程。
- SOCKS5 连接失败日志会显示请求的域名或 IP 及端口，并过滤用于关联目标的 OpenSSH 调试输出。
- 新建、编辑规则时可勾选“调试”，为该隧道输出完整的 OpenSSH DEBUG2 日志；该选项随规则持久化。
- 启动/停止按钮随所选隧道状态联动，禁止重复启动或停止。
- 可选私钥和 SSH 自定义端口。
- 启动时检查全部持久化规则，未运行的隧道会自动启动。
- JSON 配置原子写入、`fsync` 和双份备份，主配置损坏时自动恢复。
- 使用 `QProcess(program, arguments)`，不经过 shell。
- 应用图标内嵌到程序中，支持 Linux、macOS 和 Windows 多 DPI 显示；Windows
  可执行文件同时携带 PE 图标资源。

## 分层

```text
main.php / app/
  TypePHP：业务规则、CRUD、持久化、SSH argv、状态机
           │
php-src/qt_tunnel.stub.php
  原生函数声明
           │
cpp-src/qt_tunnel.cc
  Qt Widgets、对话框、事件队列、QProcess
```

C++ 层不会拼接 SSH 命令，也不会读写配置文件。对话框提交的数据以事件返回给
TypePHP，经过验证后才会写入仓库。

## 依赖

- TypePHP 编译器及其 PHP 8.4/Phpx 构建环境
- Qt 6 Widgets 开发包
- OpenSSH 客户端（运行时需要 `ssh` 位于 `PATH`）

Ubuntu/Debian：

```bash
sudo apt install qt6-base-dev openssh-client
```

## 构建

项目默认使用 Debian/Ubuntu 的 Qt 6 系统路径：

```bash
php ../../bin/tpc.php project.yml --debug -o ssh_tunnel_manager
./ssh_tunnel_manager
```

Qt 安装在其他目录时，运行下面的命令获取参数，并相应调整 `project.yml`：

```bash
pkg-config --cflags --libs Qt6Widgets
```

Windows 使用 Qt 6 MSVC SDK 时，将 `cxx-flags` 改为 Qt 的 `include`、
`include/QtCore`、`include/QtGui`、`include/QtWidgets`，将 `ld-flags` 改为
`Qt6Widgets.lib`、`Qt6Gui.lib`、`Qt6Core.lib`。部署时执行：

```powershell
windeployqt ssh_tunnel_manager.exe
```

Windows 10/11 可在“可选功能”中安装 OpenSSH Client。

## 配置文件

- Linux/macOS：`$XDG_CONFIG_HOME/typephp/ssh-tunnel-manager.json`，未设置时使用
  `~/.config/typephp/ssh-tunnel-manager.json`
- Windows：`%APPDATA%\TypePHP\ssh-tunnel-manager.json`
- 测试或便携运行：通过 `TYPEPHP_SSH_TUNNEL_CONFIG` 指定完整路径

同目录下的 `.bak` 文件是完整镜像备份。读取主配置失败时，应用会验证备份并
自动恢复主文件。所有成功保存的规则都会在下一次启动时自动建立，不再提供
容易造成遗漏的单条“自动启动”开关。

配置中不保存密码。推荐使用 SSH 私钥和 `ssh-agent`；应用为避免弹出不可见的
终端密码提示，固定启用 `BatchMode=yes`。

远程转发是否能监听非回环地址由服务器的 `sshd_config` 中 `GatewayPorts`
设置决定；本工具不会绕过服务器安全策略。

## 业务层测试

该测试不依赖 Qt，可以直接用 PHP 执行：

```bash
php tests/domain_test.php
php tests/startup_test.php
```
