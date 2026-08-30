# TypePHP Ocean Odyssey

Godot + TypePHP open ocean demo with procedural ocean, sky, clouds, rain, islands, navigation markers, and a controllable sailboat.

Godot owns rendering, input, camera, weather presentation, and procedural meshes. TypePHP owns deterministic world data and weather transitions once the bridge DLL is built. The Godot scene still runs with fallback data if the native bridge is not present.

## Run in Godot

```powershell
D:\workspace\godot\Godot_v4.7-stable_win64_console.exe --path examples\ocean-godot
```

Controls:

- `W/A/S/D` sail relative to the camera
- `Shift` boost
- Mouse to orbit the camera
- `Esc` release or capture the mouse

## Build TypePHP library

```powershell
php bin\compiler.php examples\ocean-godot\typephp.yml -f
Copy-Item -LiteralPath typephp_ocean.dll -Destination examples\ocean-godot\bin\typephp_ocean.dll -Force
```

## Build Godot GDExtension

```powershell
cmake -S examples\ocean-godot\gdextension-src -B examples\ocean-godot\gdextension-src\build-nmake -G "NMake Makefiles" -DCMAKE_TOOLCHAIN_FILE=D:\workspace\vcpkg\scripts\buildsystems\vcpkg.cmake -DVCPKG_TARGET_TRIPLET=x64-windows -DCMAKE_BUILD_TYPE=Release
cmake --build examples\ocean-godot\gdextension-src\build-nmake --config Release
```

The bridge outputs:

```text
examples\ocean-godot\bin\typephp_ocean_godot_bridge.dll
```
