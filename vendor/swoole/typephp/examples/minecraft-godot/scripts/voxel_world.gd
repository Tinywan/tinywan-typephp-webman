extends Node3D
class_name VoxelWorld

const BLOCK_SIZE := 0.6
const CHUNK_SIZE := 16
const WORLD_RADIUS := 28
const MAX_HEIGHT := 8
const WATER_LEVEL := 4
const WATER_SURFACE_OFFSET := 0.48
const ATLAS_SIZE := 16.0
const ATLAS_PADDING := 1.0 / 2048.0

@export var bridge_path: NodePath

enum BlockType {
	GRASS = 1,
	SAND = 2,
	STONE = 3,
	WOOD = 5,
	DIRT = 7,
	PLANK = 8,
	SNOW = 9,
	GLASS = 10,
	COBBLE = 11,
	LEAF = 15,
	WATER = 64,
}

var blocks: Dictionary = {}
var water_blocks: Dictionary = {}
var chunks: Dictionary = {}
var materials: Dictionary = {}
var atlas_material: ShaderMaterial
var cloud_mesh: PlaneMesh

var block_tiles := {
	BlockType.GRASS: [16, 16, 32, 0, 16, 16],
	BlockType.SAND: [1, 1, 1, 1, 1, 1],
	BlockType.STONE: [2, 2, 2, 2, 2, 2],
	BlockType.WOOD: [20, 20, 36, 4, 20, 20],
	BlockType.DIRT: [6, 6, 6, 6, 6, 6],
	BlockType.PLANK: [7, 7, 7, 7, 7, 7],
	BlockType.SNOW: [24, 24, 40, 8, 24, 24],
	BlockType.GLASS: [9, 9, 9, 9, 9, 9],
	BlockType.COBBLE: [10, 10, 10, 10, 10, 10],
	BlockType.LEAF: [14, 14, 14, 14, 14, 14],
}


func _ready() -> void:
	cloud_mesh = PlaneMesh.new()
	cloud_mesh.size = Vector2(72.0, 72.0)
	_create_materials()
	_create_sky_dome()
	if not _generate_world_from_bridge():
		_generate_world()
	_build_all_chunks()
	_create_cloud_layers()


func _create_materials() -> void:
	atlas_material = _make_atlas_material()
	for type in block_tiles.keys():
		materials[type] = atlas_material
	materials[BlockType.WATER] = _make_water_material()


func _make_atlas_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode cull_back, diffuse_lambert, specular_disabled;

uniform sampler2D atlas_texture : filter_nearest, repeat_disable;

void fragment() {
	vec4 tex = texture(atlas_texture, UV);
	if (tex.r > 0.98 && tex.g < 0.02 && tex.b > 0.98) {
		discard;
	}
	ALBEDO = tex.rgb * COLOR.rgb;
	ROUGHNESS = 0.95;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	material.set_shader_parameter("atlas_texture", load("res://assets/craft/texture.png"))
	return material


func _make_water_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode blend_mix, depth_prepass_alpha, cull_back, specular_schlick_ggx;

uniform vec4 shallow_color : source_color = vec4(0.18, 0.58, 0.82, 0.58);
uniform vec4 deep_color : source_color = vec4(0.02, 0.24, 0.42, 0.72);
uniform float wave_height = 0.055;
uniform float wave_speed = 1.4;

void vertex() {
	float wave_a = sin((VERTEX.x * 3.7 + TIME * wave_speed) + VERTEX.z * 1.4);
	float wave_b = cos((VERTEX.z * 4.1 + TIME * wave_speed * 0.8) + VERTEX.x * 1.8);
	VERTEX.y += (wave_a + wave_b) * wave_height;
}

void fragment() {
	float ripple = sin((UV.x + UV.y) * 18.0 + TIME * 2.2) * 0.5 + 0.5;
	ALBEDO = mix(deep_color.rgb, shallow_color.rgb, 0.55 + ripple * 0.18);
	ALPHA = shallow_color.a;
	ROUGHNESS = 0.18;
	SPECULAR = 0.85;
	METALLIC = 0.0;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _make_cloud_material(speed: float, density: float) -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode blend_mix, depth_draw_never, cull_disabled, unshaded;

uniform vec4 cloud_color : source_color = vec4(1.0, 1.0, 1.0, 0.68);
uniform float drift_speed = 0.018;
uniform float density = 0.54;

float cloud_noise(vec2 p) {
	float a = sin(p.x * 7.0 + p.y * 2.3);
	float b = sin(p.x * 3.1 - p.y * 8.4);
	float c = sin((p.x + p.y) * 11.0);
	return (a + b + c) / 6.0 + 0.5;
}

void fragment() {
	vec2 uv = UV + vec2(TIME * drift_speed, TIME * drift_speed * 0.28);
	float n1 = cloud_noise(uv);
	float n2 = cloud_noise(uv * 2.2 + vec2(0.31, 0.72));
	float shape = smoothstep(density, 1.0, n1 * 0.72 + n2 * 0.38);
	ALBEDO = cloud_color.rgb;
	ALPHA = shape * cloud_color.a;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	material.set_shader_parameter("drift_speed", speed)
	material.set_shader_parameter("density", density)
	return material


func _create_sky_dome() -> void:
	var dome := MeshInstance3D.new()
	dome.name = "SkyDome"
	var sphere := SphereMesh.new()
	sphere.radius = 90.0
	sphere.height = 90.0
	sphere.radial_segments = 64
	sphere.rings = 24
	dome.mesh = sphere
	dome.cast_shadow = GeometryInstance3D.SHADOW_CASTING_SETTING_OFF
	dome.material_override = _make_sky_dome_material()
	add_child(dome)


func _make_sky_dome_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode unshaded, cull_front, depth_draw_never, fog_disabled;

uniform vec4 top_color : source_color = vec4(0.18, 0.50, 0.95, 1.0);
uniform vec4 horizon_color : source_color = vec4(0.72, 0.90, 1.0, 1.0);
uniform vec4 sun_color : source_color = vec4(1.0, 0.86, 0.42, 1.0);
uniform vec3 sun_dir = vec3(-0.45, 0.62, -0.64);

void fragment() {
	vec3 view_dir = normalize(VIEW);
	float up = clamp(view_dir.y * 0.5 + 0.5, 0.0, 1.0);
	vec3 sky = mix(horizon_color.rgb, top_color.rgb, smoothstep(0.18, 1.0, up));
	float sun = pow(max(dot(normalize(-view_dir), normalize(sun_dir)), 0.0), 480.0);
	ALBEDO = sky + sun_color.rgb * sun * 1.6;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _create_cloud_layers() -> void:
	var cloud_configs := [
		{"height": 14.0, "offset": Vector3(0, 0, 0), "speed": 0.016, "density": 0.54},
		{"height": 18.0, "offset": Vector3(14, 0, -18), "speed": 0.011, "density": 0.58},
	]
	for config in cloud_configs:
		var cloud := MeshInstance3D.new()
		cloud.name = "CloudLayer"
		cloud.mesh = cloud_mesh
		cloud.position = Vector3(config["offset"].x, config["height"], config["offset"].z)
		cloud.material_override = _make_cloud_material(config["speed"], config["density"])
		add_child(cloud)


func _generate_world() -> void:
	for x in range(-WORLD_RADIUS, WORLD_RADIUS + 1):
		for z in range(-WORLD_RADIUS, WORLD_RADIUS + 1):
			var h := _height_at(x, z)
			var water_area := _is_water_area(x, z)
			for y in range(0, h + 1):
				var type := BlockType.STONE
				if y == h:
					type = BlockType.DIRT if water_area else BlockType.GRASS
				elif y >= h - 2:
					type = BlockType.DIRT
				add_block(Vector3i(x, y, z), type, false)
			if water_area:
				add_block(Vector3i(x, WATER_LEVEL, z), BlockType.WATER, false)
	_generate_tree(Vector3i(-7, _height_at(-7, -4) + 1, -4))
	_generate_tree(Vector3i(8, _height_at(8, 5) + 1, 5))
	_generate_tree(Vector3i(2, _height_at(2, -10) + 1, -10))


func _generate_world_from_bridge() -> bool:
	if bridge_path == NodePath(""):
		return false
	var bridge := get_node_or_null(bridge_path)
	if bridge == null or not bridge.has_method("generate_world"):
		return false

	var generated: Array = bridge.generate_world(WORLD_RADIUS)
	if generated.is_empty():
		return false

	for block in generated:
		if not block is Dictionary:
			continue
		add_block(
			Vector3i(int(block.get("x", 0)), int(block.get("y", 0)), int(block.get("z", 0))),
			int(block.get("type", BlockType.GRASS)),
			false
		)

	_generate_tree(Vector3i(-7, _height_at(-7, -4) + 1, -4))
	_generate_tree(Vector3i(8, _height_at(8, 5) + 1, 5))
	_generate_tree(Vector3i(2, _height_at(2, -10) + 1, -10))
	return true


func _height_at(x: int, z: int) -> int:
	var rolling := sin(float(x) * 0.34) * 1.6 + cos(float(z) * 0.28) * 1.4
	var ridge := sin(float(x + z) * 0.18) * 1.2
	var height := clampi(3 + int(round(rolling + ridge)), 1, MAX_HEIGHT)
	if _is_water_area(x, z):
		height = clampi(height - 2, 1, WATER_LEVEL - 1)
	return height


func _is_water_area(x: int, z: int) -> bool:
	var spawn_lake := (x - 7) * (x - 7) + (z - 7) * (z - 7) <= 28
	if spawn_lake:
		return true
	var center := sin(float(z) * 0.22) * 5.0 + sin(float(z) * 0.07) * 2.0
	var width := 3.4 + cos(float(z) * 0.13) * 1.0
	return abs(float(x) - center) <= width


func _generate_tree(base: Vector3i) -> void:
	for y in range(0, 4):
		add_block(base + Vector3i(0, y, 0), BlockType.WOOD, false)
	for x in range(-2, 3):
		for y in range(2, 5):
			for z in range(-2, 3):
				if abs(x) + abs(z) + max(0, y - 3) <= 4:
					add_block(base + Vector3i(x, y, z), BlockType.LEAF, false)


func add_block(pos: Vector3i, type: int = BlockType.GRASS, rebuild := true) -> bool:
	if type == BlockType.WATER:
		if water_blocks.has(pos):
			return false
		water_blocks[pos] = true
	else:
		if blocks.has(pos):
			return false
		blocks[pos] = type
		water_blocks.erase(pos)

	if rebuild:
		_rebuild_related_chunks(pos)
	return true


func remove_block(pos: Vector3i) -> bool:
	if not blocks.has(pos):
		return false
	blocks.erase(pos)
	_rebuild_related_chunks(pos)
	return true


func break_from_camera(camera: Camera3D, max_distance: float = 5.5) -> bool:
	var hit := _raycast_from_camera(camera, max_distance)
	if hit.is_empty():
		return false
	var normal := hit.normal as Vector3
	var pos := _world_to_block_pos(hit.position - normal * 0.02)
	return remove_block(pos)


func place_from_camera(camera: Camera3D, max_distance: float = 5.5) -> bool:
	var hit := _raycast_from_camera(camera, max_distance)
	if hit.is_empty():
		return false
	var normal := Vector3i(roundi(hit.normal.x), roundi(hit.normal.y), roundi(hit.normal.z))
	var base := _world_to_block_pos(hit.position - hit.normal * 0.02)
	return add_block(base + normal, BlockType.GRASS, true)


func _raycast_from_camera(camera: Camera3D, max_distance: float) -> Dictionary:
	var viewport := get_viewport()
	var center := viewport.get_visible_rect().size * 0.5
	var origin := camera.project_ray_origin(center)
	var end := origin + camera.project_ray_normal(center) * max_distance
	var query := PhysicsRayQueryParameters3D.create(origin, end)
	query.collide_with_areas = false
	query.collide_with_bodies = true
	return get_world_3d().direct_space_state.intersect_ray(query)


func _world_to_block_pos(world_pos: Vector3) -> Vector3i:
	return Vector3i(
		floori(world_pos.x / BLOCK_SIZE + 0.5),
		floori(world_pos.y / BLOCK_SIZE + 0.5),
		floori(world_pos.z / BLOCK_SIZE + 0.5)
	)


func _chunk_key(pos: Vector3i) -> Vector2i:
	return Vector2i(floori(float(pos.x) / CHUNK_SIZE), floori(float(pos.z) / CHUNK_SIZE))


func _build_all_chunks() -> void:
	for chunk in chunks.values():
		(chunk as Node).queue_free()
	chunks.clear()

	var keys := {}
	for pos in blocks.keys():
		keys[_chunk_key(pos)] = true
	for pos in water_blocks.keys():
		keys[_chunk_key(pos)] = true

	for key in keys.keys():
		_rebuild_chunk(key)


func _rebuild_related_chunks(pos: Vector3i) -> void:
	var keys := {_chunk_key(pos): true}
	if pos.x % CHUNK_SIZE == 0:
		keys[_chunk_key(pos + Vector3i(-1, 0, 0))] = true
	if pos.x % CHUNK_SIZE == CHUNK_SIZE - 1:
		keys[_chunk_key(pos + Vector3i(1, 0, 0))] = true
	if pos.z % CHUNK_SIZE == 0:
		keys[_chunk_key(pos + Vector3i(0, 0, -1))] = true
	if pos.z % CHUNK_SIZE == CHUNK_SIZE - 1:
		keys[_chunk_key(pos + Vector3i(0, 0, 1))] = true

	for key in keys.keys():
		_rebuild_chunk(key)


func _rebuild_chunk(key: Vector2i) -> void:
	if chunks.has(key):
		(chunks[key] as Node).queue_free()
		chunks.erase(key)

	var start_x := key.x * CHUNK_SIZE
	var end_x := start_x + CHUNK_SIZE - 1
	var start_z := key.y * CHUNK_SIZE
	var end_z := start_z + CHUNK_SIZE - 1

	var surface_data := {}
	for type in block_tiles.keys():
		surface_data[type] = _new_surface_data()
	var water_data := _new_surface_data()
	var collision_faces := PackedVector3Array()
	var has_geometry := false

	for pos in blocks.keys():
		if pos.x < start_x or pos.x > end_x or pos.z < start_z or pos.z > end_z:
			continue
		var type: int = blocks[pos]
		if not surface_data.has(type):
			continue
		for face in _visible_faces(pos):
			_add_cube_face(surface_data[type], pos, face)
			_add_cube_face_to_collision(collision_faces, pos, face)
			has_geometry = true

	for pos in water_blocks.keys():
		if pos.x < start_x or pos.x > end_x or pos.z < start_z or pos.z > end_z:
			continue
		_add_water_face(water_data, pos)
		has_geometry = true

	if not has_geometry:
		return

	var body := StaticBody3D.new()
	body.name = "Chunk_%d_%d" % [key.x, key.y]
	body.set_meta("chunk_key", key)
	add_child(body)

	var mesh := ArrayMesh.new()
	var surface_index := 0
	for type in block_tiles.keys():
		if _commit_surface(mesh, surface_data[type]):
			mesh.surface_set_material(surface_index, materials[type])
			surface_index += 1
	if _commit_surface(mesh, water_data):
		mesh.surface_set_material(surface_index, materials[BlockType.WATER])

	var mesh_instance := MeshInstance3D.new()
	mesh_instance.mesh = mesh
	body.add_child(mesh_instance)

	if not collision_faces.is_empty():
		var shape := ConcavePolygonShape3D.new()
		shape.set_faces(collision_faces)
		var collision := CollisionShape3D.new()
		collision.shape = shape
		body.add_child(collision)

	chunks[key] = body


func _new_surface_data() -> Dictionary:
	return {
		"vertices": PackedVector3Array(),
		"normals": PackedVector3Array(),
		"uvs": PackedVector2Array(),
		"colors": PackedColorArray(),
		"indices": PackedInt32Array(),
	}


func _commit_surface(mesh: ArrayMesh, data: Dictionary) -> bool:
	var vertices: PackedVector3Array = data["vertices"]
	if vertices.is_empty():
		return false
	var arrays := []
	arrays.resize(Mesh.ARRAY_MAX)
	arrays[Mesh.ARRAY_VERTEX] = vertices
	arrays[Mesh.ARRAY_NORMAL] = data["normals"]
	arrays[Mesh.ARRAY_TEX_UV] = data["uvs"]
	var colors: PackedColorArray = data["colors"]
	if colors.size() == vertices.size():
		arrays[Mesh.ARRAY_COLOR] = colors
	arrays[Mesh.ARRAY_INDEX] = data["indices"]
	mesh.add_surface_from_arrays(Mesh.PRIMITIVE_TRIANGLES, arrays)
	return true


func _visible_faces(pos: Vector3i) -> Array:
	var faces := []
	for face in _face_defs():
		var direction: Vector3i = face["dir"]
		var neighbor := pos + direction
		if not blocks.has(neighbor):
			faces.append(face)
	return faces


func _face_defs() -> Array:
	var h := BLOCK_SIZE * 0.5
	return [
		{"dir": Vector3i(1, 0, 0), "tile_face": 1, "normal": Vector3(1, 0, 0), "corners": [Vector3(h, -h, -h), Vector3(h, h, -h), Vector3(h, h, h), Vector3(h, -h, h)]},
		{"dir": Vector3i(-1, 0, 0), "tile_face": 0, "normal": Vector3(-1, 0, 0), "corners": [Vector3(-h, -h, h), Vector3(-h, h, h), Vector3(-h, h, -h), Vector3(-h, -h, -h)]},
		{"dir": Vector3i(0, 1, 0), "tile_face": 2, "normal": Vector3(0, 1, 0), "corners": [Vector3(-h, h, -h), Vector3(-h, h, h), Vector3(h, h, h), Vector3(h, h, -h)]},
		{"dir": Vector3i(0, -1, 0), "tile_face": 3, "normal": Vector3(0, -1, 0), "corners": [Vector3(-h, -h, h), Vector3(-h, -h, -h), Vector3(h, -h, -h), Vector3(h, -h, h)]},
		{"dir": Vector3i(0, 0, 1), "tile_face": 4, "normal": Vector3(0, 0, 1), "corners": [Vector3(h, -h, h), Vector3(h, h, h), Vector3(-h, h, h), Vector3(-h, -h, h)]},
		{"dir": Vector3i(0, 0, -1), "tile_face": 5, "normal": Vector3(0, 0, -1), "corners": [Vector3(-h, -h, -h), Vector3(-h, h, -h), Vector3(h, h, -h), Vector3(h, -h, -h)]},
	]


func _add_cube_face(data: Dictionary, pos: Vector3i, face: Dictionary) -> void:
	var base_index := (data["vertices"] as PackedVector3Array).size()
	var center := Vector3(pos) * BLOCK_SIZE
	var corners: Array = face["corners"]
	var normal: Vector3 = face["normal"]
	var type: int = blocks[pos]
	var tiles: Array = block_tiles[type]
	var tile_index: int = tiles[int(face["tile_face"])]
	var uvs := _tile_uvs(tile_index)
	var shade := _face_shade(normal)
	var color := Color(shade, shade, shade, 1.0)
	for i in range(4):
		data["vertices"].append(center + corners[i])
		data["normals"].append(normal)
		data["uvs"].append(uvs[i])
		data["colors"].append(color)
	for i in [0, 1, 2, 0, 2, 3]:
		data["indices"].append(base_index + i)


func _face_shade(normal: Vector3) -> float:
	if normal.y > 0.5:
		return 1.0
	if normal.y < -0.5:
		return 0.48
	if abs(normal.x) > 0.5:
		return 0.76
	return 0.68


func _tile_uvs(tile_index: int) -> Array:
	var tile_size := 1.0 / ATLAS_SIZE
	var atlas_column := tile_index % int(ATLAS_SIZE)
	var craft_row_from_bottom := int(tile_index / int(ATLAS_SIZE))
	var godot_row_from_top := int(ATLAS_SIZE) - 1 - craft_row_from_bottom
	var u0 := float(atlas_column) * tile_size + ATLAS_PADDING
	var v0 := float(godot_row_from_top) * tile_size + ATLAS_PADDING
	var u1 := u0 + tile_size - ATLAS_PADDING * 2.0
	var v1 := v0 + tile_size - ATLAS_PADDING * 2.0
	return [Vector2(u0, v1), Vector2(u0, v0), Vector2(u1, v0), Vector2(u1, v1)]


func _add_cube_face_to_collision(collision_faces: PackedVector3Array, pos: Vector3i, face: Dictionary) -> void:
	var center := Vector3(pos) * BLOCK_SIZE
	var corners: Array = face["corners"]
	for i in [0, 1, 2, 0, 2, 3]:
		collision_faces.append(center + corners[i])


func _add_water_face(data: Dictionary, pos: Vector3i) -> void:
	var h := BLOCK_SIZE * 0.5
	var y := BLOCK_SIZE * WATER_SURFACE_OFFSET
	var center := Vector3(pos) * BLOCK_SIZE
	var corners := [
		Vector3(-h, y, -h),
		Vector3(-h, y, h),
		Vector3(h, y, h),
		Vector3(h, y, -h),
	]
	var base_index := (data["vertices"] as PackedVector3Array).size()
	var uvs := [Vector2(0, 1), Vector2(0, 0), Vector2(1, 0), Vector2(1, 1)]
	for i in range(4):
		data["vertices"].append(center + corners[i])
		data["normals"].append(Vector3.UP)
		data["uvs"].append(uvs[i])
	for i in [0, 1, 2, 0, 2, 3]:
		data["indices"].append(base_index + i)
