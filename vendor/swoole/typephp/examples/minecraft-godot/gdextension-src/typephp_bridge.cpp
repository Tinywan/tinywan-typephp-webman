#include "typephp_bridge.hpp"

#include <godot_cpp/core/class_db.hpp>
#include <godot_cpp/variant/dictionary.hpp>

#include <algorithm>

#ifdef _WIN32
extern "C" IMAGE_DOS_HEADER __ImageBase;
#endif

namespace godot {

void TypePhpBridge::_bind_methods()
{
	ClassDB::bind_method(D_METHOD("generate_world", "radius"), &TypePhpBridge::generate_world);
}

TypePhpBridge::TypePhpBridge() = default;

TypePhpBridge::~TypePhpBridge()
{
#ifdef _WIN32
	if (library != nullptr) {
		FreeLibrary(library);
		library = nullptr;
	}
#endif
}

bool TypePhpBridge::ensure_loaded()
{
#ifndef _WIN32
	return false;
#else
	if (library != nullptr) {
		return world_init != nullptr && block_type_at != nullptr;
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
	path += L"typephp_world.dll";

	library = LoadLibraryW(path.c_str());
	if (library == nullptr) {
		return false;
	}

	world_init = reinterpret_cast<FnInit>(GetProcAddress(library, "typephp_world_init"));
	block_type_at = reinterpret_cast<FnBlockTypeAt>(GetProcAddress(library, "typephp_world_block_type_at"));
	return world_init != nullptr && block_type_at != nullptr && world_init() != 0;
#endif
}

Array TypePhpBridge::generate_world(int radius)
{
	Array blocks;
	if (!ensure_loaded()) {
		return blocks;
	}

	radius = std::clamp(radius, 1, 32);
	for (int x = -radius; x <= radius; x++) {
		for (int z = -radius; z <= radius; z++) {
			for (int y = 0; y <= 22; y++) {
				const int type = block_type_at(x, y, z);
				if (type < 0) {
					continue;
				}

				Dictionary block;
				block["x"] = x;
				block["y"] = y;
				block["z"] = z;
				block["type"] = type;
				blocks.append(block);
			}
		}
	}
	return blocks;
}

} // namespace godot
