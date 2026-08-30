#include "typephp_ocean_bridge.hpp"

#include <godot_cpp/core/class_db.hpp>
#include <godot_cpp/variant/dictionary.hpp>

#include <cmath>

#ifdef _WIN32
extern "C" IMAGE_DOS_HEADER __ImageBase;
#endif

namespace godot {

void TypePhpOceanBridge::_bind_methods()
{
	ClassDB::bind_method(D_METHOD("get_islands", "radius"), &TypePhpOceanBridge::get_islands);
	ClassDB::bind_method(D_METHOD("get_markers", "radius"), &TypePhpOceanBridge::get_markers);
	ClassDB::bind_method(D_METHOD("choose_next_weather", "current", "roll"), &TypePhpOceanBridge::choose_next_weather);
}

TypePhpOceanBridge::TypePhpOceanBridge() = default;

TypePhpOceanBridge::~TypePhpOceanBridge()
{
#ifdef _WIN32
	if (library != nullptr) {
		FreeLibrary(library);
		library = nullptr;
	}
#endif
}

bool TypePhpOceanBridge::ensure_loaded()
{
#ifndef _WIN32
	return false;
#else
	if (library != nullptr) {
		return ocean_init != nullptr;
	}

	wchar_t module_path[MAX_PATH];
	const DWORD len = GetModuleFileNameW(reinterpret_cast<HMODULE>(&__ImageBase), module_path, MAX_PATH);
	if (len == 0 || len >= MAX_PATH) {
		return false;
	}

	std::wstring path(module_path, len);
	const size_t slash = path.find_last_of(L"\\/");
	if (slash != std::wstring::npos) {
		path.resize(slash + 1);
	} else {
		path.clear();
	}
	path += L"typephp_ocean.dll";

	library = LoadLibraryW(path.c_str());
	if (library == nullptr) {
		return false;
	}

	ocean_init = reinterpret_cast<FnInit>(GetProcAddress(library, "typephp_ocean_init"));
	island_count = reinterpret_cast<FnCount>(GetProcAddress(library, "typephp_ocean_island_count"));
	island_x = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_island_x"));
	island_z = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_island_z"));
	island_radius = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_island_radius"));
	island_height = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_island_height"));
	island_seed = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_island_seed"));
	marker_count = reinterpret_cast<FnCount>(GetProcAddress(library, "typephp_ocean_marker_count"));
	marker_x = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_marker_x"));
	marker_z = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_marker_z"));
	marker_type = reinterpret_cast<FnIntAt>(GetProcAddress(library, "typephp_ocean_marker_type"));
	marker_size = reinterpret_cast<FnDoubleAt>(GetProcAddress(library, "typephp_ocean_marker_size"));
	choose_weather = reinterpret_cast<FnWeather>(GetProcAddress(library, "typephp_ocean_choose_next_weather"));

	return ocean_init != nullptr && island_count != nullptr && island_x != nullptr && island_z != nullptr &&
		   island_radius != nullptr && island_height != nullptr && island_seed != nullptr &&
		   marker_count != nullptr && marker_x != nullptr && marker_z != nullptr &&
		   marker_type != nullptr && marker_size != nullptr && choose_weather != nullptr &&
		   ocean_init() != 0;
#endif
}

Array TypePhpOceanBridge::get_islands(double radius)
{
	Array result;
	if (!ensure_loaded()) {
		return result;
	}

	const int count = island_count();
	for (int i = 0; i < count; i++) {
		const double x = island_x(i);
		const double z = island_z(i);
		if (std::sqrt(x * x + z * z) > radius) {
			continue;
		}
		Dictionary island;
		island["x"] = x;
		island["z"] = z;
		island["radius"] = island_radius(i);
		island["height"] = island_height(i);
		island["seed"] = island_seed(i);
		result.append(island);
	}
	return result;
}

Array TypePhpOceanBridge::get_markers(double radius)
{
	Array result;
	if (!ensure_loaded()) {
		return result;
	}

	const int count = marker_count();
	for (int i = 0; i < count; i++) {
		const double x = marker_x(i);
		const double z = marker_z(i);
		if (std::sqrt(x * x + z * z) > radius) {
			continue;
		}
		Dictionary marker;
		marker["x"] = x;
		marker["z"] = z;
		marker["type"] = marker_type(i);
		marker["size"] = marker_size(i);
		result.append(marker);
	}
	return result;
}

int TypePhpOceanBridge::choose_next_weather(int current, double roll)
{
	if (!ensure_loaded()) {
		return current;
	}
	return choose_weather(current, roll);
}

} // namespace godot
