# TypePHP Minecraft Demo

这是一个不依赖 Godot 的方块世界渲染示例，技术路线参考 Craft：

- Win32/WGL 创建窗口和 OpenGL 上下文。
- 使用 Craft 的 `texture.png` 方块 atlas、`sky.png` 天空贴图和 lodepng 加载 PNG。
- 使用方块六面 tile 映射、可见面剔除、植物交叉面、水面和云层透明绘制。
- PHP/TypePHP 负责世界生成、chunk 队列、角色移动等业务逻辑。
- C++ 只负责窗口、输入、OpenGL 渲染、贴图加载和 chunk mesh 缓存。
- 渲染时参考 Craft 的 chunk 可见性策略，只绘制相机附近且位于视野方向内的 chunk。

当前范围只实现基础世界画面渲染：

- 天空贴图背景
- 河流和湖泊
- 山峰和高地
- 草地、沙地、泥土、石头、雪地
- 树干、树叶、草和花朵
- 云层和远处线性雾
- 第一人称飞行观察
- 居中准星
- 初始化进度 UI
- chunk display list 缓存、视野剔除和透明层远近排序

不包含联机、背包、建造、存档等复杂系统。

## 编译

```powershell
php bin\compiler.php examples\minecraft-demo\project.yml
```

编译产物输出到仓库根目录：

```text
minecraft_demo.exe
```

## 运行

```powershell
.\minecraft_demo.exe
```

## 操作

- `W/A/S/D` 移动
- 鼠标移动视角
- `Space` 上升
- `Shift` 加速并下降
- `Esc` 弹出退出确认框

## 项目结构

```text
main.php                         PHP 世界规则和主循环
php-src/craft.stub.php           C++ 渲染 API 的 PHP 声明
cpp-src/craft_backend.cc         Win32/WGL/OpenGL 后端
deps/lodepng/                    PNG 加载
textures/texture.png             Craft 方块 atlas
textures/sky.png                 Craft 天空贴图
LICENSE.Craft.md                 Craft MIT License
```

## 授权

`textures/texture.png`、`textures/sky.png` 和 lodepng 来自 Craft 项目及其依赖。Craft 使用 MIT License，许可证已保留在 `LICENSE.Craft.md`。
