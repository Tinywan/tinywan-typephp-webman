# TypePHP Minecraft Demo

这是一个 demo 级 Godot 方块世界示例，用来验证：

- Godot 负责窗口、3D 渲染、输入、水面 shader 和场景表现。
- TypePHP/PHP 负责世界生成、地形高度、河流位置、水块类型等业务逻辑。
- C++ 只做底层对接：GDExtension、DLL 加载、C ABI 调用和数据转换。

当前版本使用 `-m lib` 编译 TypePHP 动态库，不使用 `-m ext`。

## 结构

```text
php-src/world.php                  PHP 世界生成规则
cpp-src/typephp_world_api.cc       TypePHP 动态库 C ABI 导出
gdextension-src/                   Godot GDExtension 桥接
typephp_bridge.gdextension         Godot 扩展声明
scripts/voxel_world.gd             方块、水面、树、材质与渲染
assets/craft/                      Craft 项目的 MIT 授权贴图资源
```

## 编译 TypePHP 动态库

```powershell
php bin\compiler.php examples\minecraft-godot\typephp.yml -f
Copy-Item -LiteralPath typephp_world.dll -Destination examples\minecraft-godot\bin\typephp_world.dll -Force
```

## 编译 Godot GDExtension

```powershell
cmake -S examples\minecraft-godot\gdextension-src -B examples\minecraft-godot\gdextension-src\build-nmake -G "NMake Makefiles" -DCMAKE_TOOLCHAIN_FILE=D:\workspace\vcpkg\scripts\buildsystems\vcpkg.cmake -DVCPKG_TARGET_TRIPLET=x64-windows -DCMAKE_BUILD_TYPE=Release
cmake --build examples\minecraft-godot\gdextension-src\build-nmake --config Release
```

GDExtension 会输出到：

```text
examples\minecraft-godot\bin\typephp_godot_bridge.dll
```

## 运行

```powershell
D:\workspace\godot\Godot_v4.7-stable_win64.exe --path examples\minecraft-godot
```

## 操作

- `W/A/S/D` 移动
- `Space` 跳跃
- 鼠标移动视角
- 鼠标左键挖方块
- 鼠标右键放方块
- `Esc` 释放或重新捕获鼠标

## 对接链路

```text
Godot scene
  -> TypePhpBridge GDExtension Node
  -> LoadLibrary(typephp_world.dll)
  -> typephp_world_block_type_at(x, y, z)
  -> PHP world.php 生成规则
  -> Godot 根据返回的 block type 渲染地形和水面
```

## 当前画面策略

- 方块尺寸缩小为 `0.6`，生成半径扩大为 `28`，世界密度比初版更高。
- 地形按 chunk 合并为少量 `ArrayMesh`，同一 chunk 内按材质分 surface，避免每个方块都创建独立节点。
- 碰撞按 chunk 生成 `ConcavePolygonShape3D`，编辑方块时只重建受影响 chunk。
- 方块使用 Craft 的 `texture.png` atlas，按方块六个面分别映射 tile，比如草方块的顶部、侧面、底面使用不同贴图。
- 使用 ProceduralSkyMaterial 渲染蓝天。
- 运行时生成两层半透明云面，shader 控制云形和缓慢漂移。
- 水面使用透明 shader，带轻微顶点波动和高光。

后续如果继续追求画质，可以为草地、石头、水面增加贴图/法线贴图，并加入远处低细节 chunk 或雾效过渡。

## 借鉴 Craft 的部分

`assets/craft` 中的 `texture.png`、`sky.png`、`font.png` 来自 Michael Fogleman 的 Craft 项目，原项目使用 MIT License，许可证已保留在 `assets/craft/LICENSE.md`。

当前示例借鉴了 Craft 的几个核心思路：

- 使用 16x16 tile atlas，而不是每种方块一张独立纹理。
- Craft 的 tile 编号以 atlas 底部为原点，Godot UV 以顶部为原点，渲染时已做 Y 轴翻转。
- 使用方块 registry 描述每个方块六个面的 tile。
- 透明方块和普通方块分材质处理。
- PHP 层负责世界规则：地形高度、水域、沙滩、树木、方块类型判定。
- C++/Godot 层只负责桥接、渲染、输入和碰撞。
