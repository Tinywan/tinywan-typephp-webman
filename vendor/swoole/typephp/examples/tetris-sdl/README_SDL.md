# 俄罗斯方块游戏 - SDL 版本

这是一个使用 PHP 和 C++ 实现的俄罗斯方块游戏，专为 Linux 平台设计，使用 SDL2 库进行图形渲染。

## 系统要求

- Linux 操作系统
- PHP 8.0+
- SDL2 开发库
- AOT 编译器

## 安装依赖

在 Ubuntu/Debian 系统上安装 SDL2：

```bash
sudo apt-get install libsdl2-dev
```

在 CentOS/RHEL 系统上安装 SDL2：

```bash
sudo yum install SDL2-devel
```

## 编译和运行

1. 确保已安装所有依赖
2. 使用 AOT 编译器编译项目：

```bash
php compiler.php compile examples/tetris
```

3. 运行编译后的程序：

```bash
./examples/tetris/build/tetris
```

## 控制方式

- ← → : 左右移动方块
- ↑   : 旋转方块
- ↓   : 加速下落
- 空格 : 直接落下

## 技术实现

- 游戏逻辑使用 PHP 编写
- 图形渲染和窗口管理使用 C++ 和 SDL2
- 通过 PHX 扩展桥接 PHP 和 C++

## 文件结构

- `main.php` - 主游戏逻辑（PHP）
- `php-src/` - PHP 函数声明
- `cpp-src/` - C++ 实现（SDL2 图形接口）
- `project.yml` - 项目配置文件
