#pragma once

#include <godot_cpp/classes/node.hpp>
#include <godot_cpp/variant/array.hpp>

#ifdef _WIN32
#include <windows.h>
#endif

namespace godot {

class TypePhpOceanBridge : public Node {
	GDCLASS(TypePhpOceanBridge, Node)

	using FnInit = int(__cdecl *)();
	using FnCount = int(__cdecl *)();
	using FnDoubleAt = double(__cdecl *)(int);
	using FnIntAt = int(__cdecl *)(int);
	using FnWeather = int(__cdecl *)(int, double);

#ifdef _WIN32
	HMODULE library = nullptr;
#endif
	FnInit ocean_init = nullptr;
	FnCount island_count = nullptr;
	FnDoubleAt island_x = nullptr;
	FnDoubleAt island_z = nullptr;
	FnDoubleAt island_radius = nullptr;
	FnDoubleAt island_height = nullptr;
	FnDoubleAt island_seed = nullptr;
	FnCount marker_count = nullptr;
	FnDoubleAt marker_x = nullptr;
	FnDoubleAt marker_z = nullptr;
	FnIntAt marker_type = nullptr;
	FnDoubleAt marker_size = nullptr;
	FnWeather choose_weather = nullptr;

protected:
	static void _bind_methods();

public:
	TypePhpOceanBridge();
	~TypePhpOceanBridge();

	Array get_islands(double radius);
	Array get_markers(double radius);
	int choose_next_weather(int current, double roll);

private:
	bool ensure_loaded();
};

} // namespace godot
