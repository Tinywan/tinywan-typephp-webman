# TypePHP Ocean Demo

这是一个新的 OpenGL 示例项目，场景为海洋、天空和一条可移动小船。它复用 `minecraft-demo` 的 Win32/WGL 思路，但不使用方块世界，重点展示程序化水面和天气变化。

## 特性

- PHP/TypePHP 负责游戏逻辑：小船移动、惯性、昼夜循环、随机天气切换。
- C++ 只负责底层窗口、键盘输入、OpenGL 渲染和退出确认框。
- 程序化天空：白天、夜晚、傍晚、清晨的颜色变化。
- 程序化 skybox：远景只保留镜头旋转，不参与世界平移。
- 程序化海面：动态波浪、法线扰动、漫反射、镜面高光和菲涅尔近似。
- 参考 `D:\workspace\Ocean-Simulation\Ocean_Simulation` 的 Phillips Spectrum、PBR/Fresnel、roughness/AO 和指数雾思路，移植为固定管线可运行的轻量版本。
- 天气：晴天、阴天、雨天随机变化。
- 小船支持 `W/A/S/D` 四方向移动。
- 鼠标控制自由摄像机视角。
- WASD 采用类似 Minecraft 的镜头相对移动：`W` 向镜头前方，`A/D` 横向移动。
- 开放世界分块：PHP 按小船所在 chunk 渐进生成和卸载海上对象。
- 海上对象包括浮标、灯浮标和小礁石，用于提供移动参照和远景层次。
- 新增程序化海岛和大型帆船对象，尝试接近 `1r.png` 的前景船、中景岛、远景层次构图。
- 远处线性雾效，使海平线自然融合。
- HDR 风格 tone mapping，使强高光和暗部层次更自然。
- Bloom 近似：太阳、晨昏地平线和夜间船灯有柔和光晕。
- 夜间小船灯作为点光源影响近处水面。
- 参考 `D:\workspace\volumetric-clouds` 的体积云 shader，将 coverage、wind shear、多层噪声、Beer-Lambert 透射、powder effect 和 Henyey-Greenstein 相位函数移植为固定管线可绘制的分层云近似。

## 关于 ref1.txt 建议

`ref1.txt` 建议使用 OpenGL 3.3、GLFW、GLEW/GLAD、GLM 和 stb_image。这个示例为了保持 TypePHP 示例项目轻量、可直接用当前编译器构建，继续使用 Win32/WGL 和 OpenGL 固定管线，没有引入额外第三方依赖。

已按同类思路实现：

- 天空盒式远景层
- 动态水面网格
- 多正弦波浪
- 基础光照、高光和菲涅尔近似
- 鼠标摄像机控制
- 雾效

## 关于 Ocean-Simulation

`D:\workspace\Ocean-Simulation\Ocean_Simulation` 使用现代 OpenGL shader、tessellation、OpenCL FFT、高度贴图、HDR skybox 和 PBR/IBL。当前 TypePHP demo 没有引入这些运行时依赖，而是提取了其中适合示例项目的核心算法思想：

- 多频谱波浪叠加，使用深水色散关系 `sqrt(g * k)`。
- 斜率推导法线、roughness 和 ambient occlusion。
- Fresnel-Schlick 近似。
- GGX/Reitz 风格高光分布的轻量化版本。
- 指数平方雾效，使海面远处自然融入天空。

## 关于 ref2.txt 建议

`ref2.txt` 进一步建议 PBR/IBL、FFT、HDR、Bloom、多光源和大气雾效。当前示例仍保持低依赖固定管线实现，但继续补上了可落地的近似：

- HDR 风格曝光和 tone mapping。
- 屏幕空间 Bloom 近似，不依赖 framebuffer 后处理。
- 夜间船灯点光源。
- 水面材质继续使用 Fresnel、roughness、AO 和高光分布。
- 继续保留指数平方雾效来模拟大气融合。

## 关于 volumetric-clouds

`D:\workspace\volumetric-clouds` 使用 OpenGL 4、3D 噪声纹理、compute shader 和全屏 ray marching。当前示例没有引入这些依赖，而是提取核心思路做轻量实现：

- 使用 value noise + Worley FBM 近似云密度。
- 使用 coverage 控制云层覆盖率，天气越差云层越厚。
- 使用 wind shear 和时间偏移让云层缓慢移动。
- 使用 Beer-Lambert 透射模拟云体厚度。
- 使用 powder effect 和 Henyey-Greenstein 相位函数近似云层散射。
- 使用多层半透明 quad strip 叠加，替代原先简单椭圆云。
- 海洋颜色使用深海吸收色、浅层散射、天空反射和雨天灰蓝衰减，比早期的亮青色更接近真实海面。

## 已知视觉问题修正

- 早期 skybox 使用立方体四个侧面，镜头旋转时会看到类似四面“屏障”的硬边界。现在已改为半球天空穹顶。
- 早期水面是方形网格并按单方向淡出，远处边缘可能像边界。现在改为更大范围的水面网格，并按径向距离平滑淡出。

## 编译

```powershell
php bin\compiler.php examples\ocean-demo\project.yml
```

编译产物输出到仓库根目录：

```text
ocean_demo.exe
```

## 运行

```powershell
.\ocean_demo.exe
```

## 操作

- `W/A/S/D` 移动小船
- 鼠标移动视角
- `Esc` 弹出退出确认框
