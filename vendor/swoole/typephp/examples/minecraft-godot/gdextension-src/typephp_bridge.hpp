#pragma once

#include <godot_cpp/classes/node.hpp>
#include <godot_cpp/variant/array.hpp>

#ifdef _WIN32
#include <windows.h>
#endif

namespace godot {

class TypePhpBridge : public Node {
	GDCLASS(TypePhpBridge, Node)

	using FnInit = int(__cdecl *)();
	using FnBlockTypeAt = int(__cdecl *)(int, int, int);

#ifdef _WIN32
	HMODULE library = nullptr;
#endif
	FnInit world_init = nullptr;
	FnBlockTypeAt block_type_at = nullptr;

protected:
	static void _bind_methods();

public:
	TypePhpBridge();
	~TypePhpBridge();

	Array generate_world(int radius);

private:
	bool ensure_loaded();
};

} // namespace godot
