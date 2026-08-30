extends Node3D

const OCEAN_SIZE := 960.0
const ISLAND_GRID := 44
const CLOUD_LAYER_COUNT := 3
const WEATHER_SUNNY := 0
const WEATHER_CLOUDY := 1
const WEATHER_RAIN := 2
const CAMERA_MIN_PITCH := deg_to_rad(-10.0)
const CAMERA_MAX_PITCH := deg_to_rad(28.0)

var boat_position := Vector3.ZERO
var boat_yaw := 0.0
var boat_velocity := Vector3.ZERO
var camera_yaw := 0.0
var camera_pitch := deg_to_rad(4.0)
var day_time := 0.27
var weather := WEATHER_SUNNY
var target_weather := WEATHER_CLOUDY
var weather_mix := 0.0
var next_weather_change := 14.0
var rain_amount := 0.0
var mouse_sensitivity := 0.0022
var bridge: Object

var ocean_material: ShaderMaterial
var island_material: ShaderMaterial
var sky_material: ShaderMaterial
var cloud_materials: Array[ShaderMaterial] = []
var island_root: Node3D
var marker_root: Node3D
var boat: Node3D
var sail_material: StandardMaterial3D
var ocean_tiles: Array[MeshInstance3D] = []
var sky_dome: MeshInstance3D

@onready var camera_rig: Node3D = $CameraRig
@onready var camera: Camera3D = $CameraRig/Camera3D
@onready var sun: DirectionalLight3D = $Sun
@onready var moon: DirectionalLight3D = $Moon
@onready var rain: GPUParticles3D = $Rain
@onready var world_environment: WorldEnvironment = $WorldEnvironment


func _ready() -> void:
	_ensure_input_map()
	Input.mouse_mode = Input.MOUSE_MODE_CAPTURED
	bridge = _try_create_bridge()
	_create_materials()
	_create_sky()
	_create_ocean()
	_create_islands()
	_create_markers()
	_create_boat()
	_update_environment(0.0)


func _unhandled_input(event: InputEvent) -> void:
	if event.is_action_pressed("toggle_mouse"):
		Input.mouse_mode = Input.MOUSE_MODE_VISIBLE if Input.mouse_mode == Input.MOUSE_MODE_CAPTURED else Input.MOUSE_MODE_CAPTURED
		get_viewport().set_input_as_handled()
	elif event is InputEventMouseMotion and Input.mouse_mode == Input.MOUSE_MODE_CAPTURED:
		camera_yaw -= event.relative.x * mouse_sensitivity
		camera_pitch = clampf(camera_pitch - event.relative.y * mouse_sensitivity, CAMERA_MIN_PITCH, CAMERA_MAX_PITCH)
		get_viewport().set_input_as_handled()


func _process(delta: float) -> void:
	var dt := clampf(delta, 0.001, 0.05)
	_update_boat(dt)
	_update_weather(dt)
	_update_environment(dt)
	_update_camera(dt)
	_update_ocean_tiles()
	rain.position = boat_position + Vector3(0.0, 58.0, 0.0)


func _try_create_bridge() -> Object:
	if not ClassDB.class_exists("TypePhpOceanBridge"):
		return null
	var obj: Object = ClassDB.instantiate("TypePhpOceanBridge")
	if obj is Node:
		add_child(obj)
	return obj


func _create_materials() -> void:
	ocean_material = _make_ocean_material()
	island_material = _make_island_material()
	sky_material = _make_sky_material()
	sail_material = StandardMaterial3D.new()
	sail_material.albedo_color = Color(0.94, 0.9, 0.78)
	sail_material.roughness = 0.82
	sail_material.metallic = 0.0
	sail_material.cull_mode = BaseMaterial3D.CULL_DISABLED


func _make_ocean_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode specular_schlick_ggx, cull_disabled;

uniform vec4 deep_color : source_color = vec4(0.002, 0.026, 0.105, 1.0);
uniform vec4 mid_color : source_color = vec4(0.006, 0.100, 0.290, 1.0);
uniform vec4 shallow_color : source_color = vec4(0.018, 0.260, 0.560, 1.0);
uniform vec4 storm_color : source_color = vec4(0.006, 0.026, 0.065, 1.0);
uniform float storm = 0.0;
uniform float rain = 0.0;
uniform float time_scale = 1.0;
uniform vec3 sun_dir = vec3(-0.45, 0.72, -0.54);
uniform vec3 boat_pos = vec3(0.0, 0.0, 0.0);
uniform vec2 boat_forward = vec2(0.0, -1.0);
uniform float boat_speed = 0.0;

float wave(vec2 p, vec2 d, float amp, float freq, float speed) {
	float phase = dot(p, normalize(d)) * freq + TIME * speed * time_scale;
	return sin(phase) * amp * (1.0 + storm * 0.55);
}

float hash(vec2 p) {
	return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
}

float noise(vec2 p) {
	vec2 i = floor(p);
	vec2 f = fract(p);
	f = f * f * (3.0 - 2.0 * f);
	float a = hash(i);
	float b = hash(i + vec2(1.0, 0.0));
	float c = hash(i + vec2(0.0, 1.0));
	float d = hash(i + vec2(1.0, 1.0));
	return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
}

float height_at(vec2 p) {
	float h = 0.0;
	h += wave(p, vec2(0.92, 0.28), 0.72, 0.035, 0.54);
	h += wave(p, vec2(0.38, 1.0), 0.38, 0.070, 0.86);
	h += wave(p, vec2(-0.68, 0.55), 0.16, 0.155, 1.58);
	h += wave(p, vec2(0.24, -0.92), 0.045, 0.410, 2.45);
	return h;
}

void vertex() {
	VERTEX.y += height_at(VERTEX.xz + NODE_POSITION_WORLD.xz) * 0.035;
}

void fragment() {
	vec2 p = (VERTEX.xz + NODE_POSITION_WORLD.xz);
	vec2 d0 = normalize(vec2(0.92, 0.28));
	vec2 d1 = normalize(vec2(0.38, 1.0));
	vec2 d2 = normalize(vec2(-0.68, 0.55));
	vec2 d3 = normalize(vec2(0.24, -0.92));
	float p0 = dot(p, d0) * 0.035 + TIME * 0.54;
	float p1 = dot(p, d1) * 0.070 + TIME * 0.86;
	float p2 = dot(p, d2) * 0.155 + TIME * 1.58;
	float p3 = dot(p, d3) * 0.410 + TIME * 2.45;
	float p4 = dot(p, normalize(vec2(0.78, -0.18))) * 0.82 + TIME * 3.35;
	float p5 = dot(p, normalize(vec2(-0.22, 0.96))) * 1.37 + TIME * 4.25;
	float h = sin(p0) * 0.72 + sin(p1) * 0.38 + sin(p2) * 0.16 + sin(p3) * 0.045 + sin(p4) * 0.018 + sin(p5) * 0.010;
	vec2 micro = vec2(noise(p * 0.085 + vec2(TIME * 0.08, 3.7)), noise(p * 0.095 + vec2(11.4, TIME * 0.07))) - vec2(0.5);
	vec2 slope = d0 * cos(p0) * 0.025 + d1 * cos(p1) * 0.027 + d2 * cos(p2) * 0.025 + d3 * cos(p3) * 0.018 + normalize(vec2(0.78, -0.18)) * cos(p4) * 0.015 + normalize(vec2(-0.22, 0.96)) * cos(p5) * 0.010 + micro * 0.018;
	float view_distance = length(VERTEX.xz);
	float near_detail = 1.0 - smoothstep(280.0, 820.0, view_distance);
	NORMAL = normalize(vec3(-slope.x * mix(10.0, 19.0, near_detail), 1.0, -slope.y * mix(10.0, 19.0, near_detail)));
	vec3 view_dir = normalize(VIEW);
	vec3 light_dir = normalize(sun_dir);
	vec3 half_dir = normalize(light_dir - view_dir);
	float facing = pow(1.0 - clamp(dot(view_dir, NORMAL), 0.0, 1.0), 4.0);
	float chop = clamp(length(slope) * 8.0 + abs(h) * 0.18 + storm * 0.36, 0.0, 1.0);
	float sky_reflect = pow(facing, 0.85) * (0.10 - rain * 0.05);
	float long_bands = sin(dot(p, vec2(0.010, 0.018)) + TIME * 0.16) * 0.5 + 0.5;
	vec3 sea = mix(deep_color.rgb, mid_color.rgb, 0.30 + chop * 0.16 + long_bands * 0.08);
	sea = mix(sea, shallow_color.rgb, smoothstep(0.80, 1.22, h) * 0.20 * near_detail);
	sea = mix(sea, storm_color.rgb, storm * 0.72);
	sea += vec3(0.025, 0.075, 0.14) * sky_reflect;
	float foam_lines = smoothstep(0.84, 1.0, sin(dot(p, vec2(0.055, 0.072)) + TIME * 1.35) * 0.5 + 0.5);
	float sun_glint = pow(max(dot(NORMAL, half_dir), 0.0), 110.0) * smoothstep(0.05, 0.65, light_dir.y) * (1.0 - rain * 0.75);
	float sparkle_a = smoothstep(0.88, 1.0, sin(dot(p, vec2(0.72, -0.18)) + TIME * 3.4) * 0.5 + 0.5);
	float sparkle_b = smoothstep(0.90, 1.0, sin(dot(p, vec2(-0.24, 1.18)) + TIME * 4.7) * 0.5 + 0.5);
	float grid_breakup = sin(p.x * 0.173 + p.y * 0.097 + TIME * 0.41) * 0.5 + 0.5;
	float glitter = (sparkle_a * 0.50 + sparkle_b * 0.34 + grid_breakup * 0.16) * smoothstep(0.38, 0.92, chop) * (1.0 - storm * 0.55) * near_detail;
	float crest_foam = smoothstep(0.80, 1.0, chop) * foam_lines * (0.040 + storm * 0.22) * near_detail;
	vec2 to_boat = p - boat_pos.xz;
	vec2 forward = normalize(boat_forward);
	vec2 right = vec2(forward.y, -forward.x);
	float behind = smoothstep(3.0, -18.0, dot(to_boat, forward));
	float side = abs(dot(normalize(to_boat + vec2(0.001)), right));
	float wake_wing = smoothstep(0.72, 0.46, abs(side - 0.55));
	float wake_fade = smoothstep(46.0, 3.0, length(to_boat));
	float wake_core = smoothstep(0.18, 0.02, abs(dot(to_boat, right))) * smoothstep(22.0, 3.0, length(to_boat));
	float wake = (wake_wing * 0.88 + wake_core * 0.32) * wake_fade * behind * clamp(boat_speed / 8.0, 0.0, 1.0);
	float foam = clamp(crest_foam + wake * 0.72, 0.0, 1.0);
	vec3 glint_color = vec3(0.80, 0.93, 1.0) * (sun_glint * 0.95 + glitter * 0.10);
	float horizon_blend = smoothstep(520.0, 1180.0, view_distance);
	vec3 horizon_water = vec3(0.050, 0.150, 0.310);
	ALBEDO = mix(mix(sea, vec3(0.72, 0.88, 0.94), foam) + glint_color, horizon_water, horizon_blend * (0.22 + storm * 0.20));
	ROUGHNESS = mix(0.070, 0.22, storm + rain * 0.45);
	SPECULAR = mix(0.92, 0.45, rain);
	RIM = facing * 0.28;
	RIM_TINT = 0.28;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _make_island_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode cull_back, diffuse_burley, specular_schlick_ggx;

uniform vec4 sand : source_color = vec4(0.76, 0.62, 0.36, 1.0);
uniform vec4 soil : source_color = vec4(0.62, 0.45, 0.22, 1.0);
uniform vec4 rock : source_color = vec4(0.46, 0.35, 0.21, 1.0);
uniform vec4 grass : source_color = vec4(0.12, 0.34, 0.12, 1.0);

float patch_noise(vec2 p) {
	return fract(sin(dot(floor(p), vec2(127.1, 311.7))) * 43758.5453);
}

void fragment() {
	float slope = 1.0 - clamp(NORMAL.y, 0.0, 1.0);
	float height = VERTEX.y;
	float beach = smoothstep(0.0, 1.8, height);
	vec3 color = mix(sand.rgb, soil.rgb, beach);
	color = mix(color, rock.rgb, smoothstep(0.38, 0.78, slope));
	float vegetation_noise = patch_noise(VERTEX.xz * 0.11);
	float vegetation = smoothstep(2.7, 7.0, height) * smoothstep(0.66, 0.18, slope) * smoothstep(0.36, 0.74, vegetation_noise);
	color = mix(color, grass.rgb, vegetation * 0.78);
	ALBEDO = color;
	ROUGHNESS = 0.88;
	SPECULAR = 0.25;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _make_sky_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode unshaded, cull_front, depth_draw_never, fog_disabled;

uniform float day_time = 0.28;
uniform float storm = 0.0;
uniform vec3 sun_dir = vec3(-0.45, 0.72, -0.54);

float hash(vec2 p) {
	return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
}

void fragment() {
	vec3 v = normalize(VIEW);
	float up = clamp(v.y * 0.5 + 0.5, 0.0, 1.0);
	float daylight = smoothstep(-0.16, 0.18, sun_dir.y);
	vec3 day_top = vec3(0.10, 0.46, 0.92);
	vec3 day_horizon = vec3(0.70, 0.88, 1.0);
	vec3 dusk_top = vec3(0.08, 0.12, 0.30);
	vec3 dusk_horizon = vec3(1.0, 0.44, 0.22);
	vec3 night_top = vec3(0.01, 0.018, 0.055);
	vec3 night_horizon = vec3(0.05, 0.08, 0.16);
	float dusk = 1.0 - smoothstep(0.08, 0.42, abs(sun_dir.y));
	vec3 top = mix(night_top, day_top, daylight);
	vec3 horizon = mix(night_horizon, day_horizon, daylight);
	top = mix(top, dusk_top, dusk * 0.5);
	horizon = mix(horizon, dusk_horizon, dusk);
	vec3 sky = mix(horizon, top, smoothstep(0.02, 0.92, up));
	sky += vec3(0.04, 0.10, 0.16) * smoothstep(0.36, 0.88, up) * daylight;
	sky = mix(sky, vec3(0.09, 0.11, 0.13), storm * 0.78);
	float sun = pow(max(dot(normalize(-v), normalize(sun_dir)), 0.0), 640.0) * daylight;
	float glow = pow(max(dot(normalize(-v), normalize(sun_dir)), 0.0), 14.0) * daylight;
	float stars = step(0.996, hash(floor(v.xz * 460.0))) * (1.0 - daylight) * smoothstep(0.18, 0.8, up);
	ALBEDO = sky + vec3(1.0, 0.76, 0.35) * sun * 4.0 + vec3(1.0, 0.45, 0.20) * glow * dusk * 0.55 + vec3(stars);
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _create_sky() -> void:
	sky_dome = MeshInstance3D.new()
	sky_dome.name = "SkyDome"
	var sphere := SphereMesh.new()
	sphere.radius = 900.0
	sphere.height = 900.0
	sphere.radial_segments = 64
	sphere.rings = 32
	sky_dome.mesh = sphere
	sky_dome.cast_shadow = GeometryInstance3D.SHADOW_CASTING_SETTING_OFF
	sky_dome.material_override = sky_material
	add_child(sky_dome)

	for i in range(CLOUD_LAYER_COUNT):
		var cloud := MeshInstance3D.new()
		cloud.name = "CloudLayer%d" % i
		var mesh := PlaneMesh.new()
		mesh.size = Vector2(1100.0 + i * 120.0, 1100.0 + i * 120.0)
		mesh.subdivide_width = 1
		mesh.subdivide_depth = 1
		cloud.mesh = mesh
		cloud.position = Vector3((i - 2) * 80.0, 84.0 + i * 13.0, -70.0 + i * 45.0)
		cloud.rotation.x = deg_to_rad(4.0 + i * 1.5)
		var material := _make_cloud_material(i)
		cloud_materials.append(material)
		cloud.material_override = material
		add_child(cloud)
	_create_horizon_clouds()
	_create_cirrus_clouds()


func _create_horizon_clouds() -> void:
	var material := _make_horizon_cloud_material()
	cloud_materials.append(material)
	for i in range(8):
		var cloud := MeshInstance3D.new()
		cloud.name = "HorizonCloudBand%d" % i
		var mesh := PlaneMesh.new()
		mesh.size = Vector2(320.0, 76.0)
		cloud.mesh = mesh
		var angle := TAU * float(i) / 8.0
		var pos := Vector3(sin(angle) * 620.0, 64.0 + sin(angle * 2.0) * 10.0, cos(angle) * 620.0)
		cloud.position = pos
		cloud.look_at_from_position(pos, Vector3(0.0, 58.0, 0.0), Vector3.UP)
		cloud.rotation.x += deg_to_rad(-4.0)
		cloud.material_override = material
		add_child(cloud)


func _create_cirrus_clouds() -> void:
	var material := _make_cirrus_cloud_material()
	cloud_materials.append(material)
	for i in range(3):
		var cloud := MeshInstance3D.new()
		cloud.name = "CirrusCloud%d" % i
		var mesh := PlaneMesh.new()
		mesh.size = Vector2(920.0, 210.0)
		cloud.mesh = mesh
		cloud.position = Vector3((i - 1) * 170.0, 210.0 + i * 16.0, -260.0 + i * 130.0)
		cloud.rotation = Vector3(deg_to_rad(12.0), deg_to_rad(8.0 + i * 9.0), deg_to_rad(-4.0 + i * 3.0))
		cloud.material_override = material
		add_child(cloud)


func _make_cloud_material(layer: int) -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode blend_mix, depth_draw_never, cull_disabled, unshaded, fog_disabled;

uniform float coverage = 0.42;
uniform float density = 0.64;
uniform float storm = 0.0;
uniform float speed = 0.018;
uniform vec4 bright : source_color = vec4(1.0, 1.0, 0.96, 0.68);
uniform vec4 dark : source_color = vec4(0.33, 0.39, 0.46, 0.82);

float noise(vec2 p) {
	vec2 i = floor(p);
	vec2 f = fract(p);
	f = f * f * (3.0 - 2.0 * f);
	float a = fract(sin(dot(i, vec2(127.1, 311.7))) * 43758.5453);
	float b = fract(sin(dot(i + vec2(1, 0), vec2(127.1, 311.7))) * 43758.5453);
	float c = fract(sin(dot(i + vec2(0, 1), vec2(127.1, 311.7))) * 43758.5453);
	float d = fract(sin(dot(i + vec2(1, 1), vec2(127.1, 311.7))) * 43758.5453);
	return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
}

float fbm(vec2 p) {
	float v = 0.0;
	float a = 0.5;
	for (int i = 0; i < 5; i++) {
		v += noise(p) * a;
		p *= 2.17;
		a *= 0.52;
	}
	return v;
}

void fragment() {
	vec2 uv = UV * 5.0 + vec2(TIME * speed, TIME * speed * 0.23);
	float base = fbm(uv);
	float detail = fbm(uv * 3.0 + vec2(17.0, 9.0));
	float edge = smoothstep(coverage, 1.0, base * 0.82 + detail * 0.26);
	float alpha = edge * density * (0.55 + storm * 0.42);
	vec3 color = mix(bright.rgb, dark.rgb, storm * 0.72 + (1.0 - detail) * 0.18);
	ALBEDO = color;
	ALPHA = alpha;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	material.set_shader_parameter("coverage", 0.45 + layer * 0.035)
	material.set_shader_parameter("density", 0.36 + layer * 0.065)
	material.set_shader_parameter("speed", 0.012 + layer * 0.003)
	return material


func _make_horizon_cloud_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode blend_mix, depth_draw_never, cull_disabled, unshaded, fog_disabled;

uniform float storm = 0.0;
uniform vec4 bright : source_color = vec4(1.0, 0.98, 0.91, 0.82);
uniform vec4 shade : source_color = vec4(0.52, 0.66, 0.78, 0.72);

float hash(vec2 p) {
	return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
}

float noise(vec2 p) {
	vec2 i = floor(p);
	vec2 f = fract(p);
	f = f * f * (3.0 - 2.0 * f);
	float a = hash(i);
	float b = hash(i + vec2(1.0, 0.0));
	float c = hash(i + vec2(0.0, 1.0));
	float d = hash(i + vec2(1.0, 1.0));
	return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
}

float fbm(vec2 p) {
	float v = 0.0;
	float a = 0.55;
	for (int i = 0; i < 4; i++) {
		v += noise(p) * a;
		p *= 2.1;
		a *= 0.5;
	}
	return v;
}

void fragment() {
	vec2 uv = UV;
	float mound = smoothstep(0.12, 0.36, uv.y) * smoothstep(1.0, 0.44, uv.y);
	float n = fbm(vec2(uv.x * 8.0 + TIME * 0.006, uv.y * 3.2));
	float shape = smoothstep(0.34, 0.72, n + mound * 0.42);
	float horizon_fade = smoothstep(0.02, 0.18, uv.y) * smoothstep(0.96, 0.48, uv.y);
	vec3 color = mix(shade.rgb, bright.rgb, smoothstep(0.35, 0.86, uv.y + n * 0.22));
	color = mix(color, vec3(0.42, 0.48, 0.54), storm * 0.72);
	ALBEDO = color;
	ALPHA = shape * horizon_fade * mix(0.70, 0.88, storm);
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _make_cirrus_cloud_material() -> ShaderMaterial:
	var shader := Shader.new()
	shader.code = """
shader_type spatial;
render_mode blend_mix, depth_draw_never, cull_disabled, unshaded, fog_disabled;

uniform float storm = 0.0;

float hash(vec2 p) {
	return fract(sin(dot(p, vec2(269.5, 183.3))) * 43758.5453);
}

float noise(vec2 p) {
	vec2 i = floor(p);
	vec2 f = fract(p);
	f = f * f * (3.0 - 2.0 * f);
	float a = hash(i);
	float b = hash(i + vec2(1.0, 0.0));
	float c = hash(i + vec2(0.0, 1.0));
	float d = hash(i + vec2(1.0, 1.0));
	return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
}

void fragment() {
	vec2 uv = UV;
	vec2 stretched = vec2(uv.x * 13.0 + uv.y * 4.5 + TIME * 0.01, uv.y * 2.3);
	float fibers = noise(stretched) * 0.62 + noise(stretched * 2.4 + vec2(3.1, 7.4)) * 0.30;
	float long_shape = smoothstep(0.06, 0.22, uv.y) * smoothstep(0.98, 0.34, uv.y);
	float torn_edges = smoothstep(0.48, 0.78, fibers);
	float alpha = torn_edges * long_shape * (1.0 - storm * 0.72) * 0.54;
	ALBEDO = mix(vec3(0.82, 0.91, 1.0), vec3(1.0, 0.98, 0.92), smoothstep(0.36, 0.86, uv.y));
	ALPHA = alpha;
}
"""
	var material := ShaderMaterial.new()
	material.shader = shader
	return material


func _create_ocean() -> void:
	var mesh := PlaneMesh.new()
	mesh.size = Vector2(OCEAN_SIZE, OCEAN_SIZE)
	mesh.subdivide_width = 16
	mesh.subdivide_depth = 16
	for x in range(-1, 2):
		for z in range(-1, 2):
			var tile := MeshInstance3D.new()
			tile.name = "OceanTile_%d_%d" % [x, z]
			tile.mesh = mesh
			tile.material_override = ocean_material
			tile.cast_shadow = GeometryInstance3D.SHADOW_CASTING_SETTING_OFF
			add_child(tile)
			ocean_tiles.append(tile)
	_update_ocean_tiles()


func _update_ocean_tiles() -> void:
	var center_x: float = floor(boat_position.x / OCEAN_SIZE) * OCEAN_SIZE
	var center_z: float = floor(boat_position.z / OCEAN_SIZE) * OCEAN_SIZE
	var index := 0
	for x in range(-1, 2):
		for z in range(-1, 2):
			var tile := ocean_tiles[index]
			tile.position = Vector3(center_x + float(x) * OCEAN_SIZE, 0.0, center_z + float(z) * OCEAN_SIZE)
			index += 1


func _create_islands() -> void:
	island_root = Node3D.new()
	island_root.name = "Islands"
	add_child(island_root)
	var islands: Array = _bridge_array("get_islands", [1180.0])
	if islands.is_empty():
		islands = _fallback_islands()
	for island in islands:
		if island is Dictionary:
			_add_island(island)


func _fallback_islands() -> Array:
	return [
		{"x": 170.0, "z": -210.0, "radius": 58.0, "height": 20.0, "seed": 11.0},
		{"x": -260.0, "z": 120.0, "radius": 42.0, "height": 14.0, "seed": 27.0},
		{"x": 420.0, "z": 310.0, "radius": 78.0, "height": 28.0, "seed": 43.0},
		{"x": -520.0, "z": -390.0, "radius": 64.0, "height": 22.0, "seed": 61.0},
		{"x": 720.0, "z": -110.0, "radius": 52.0, "height": 18.0, "seed": 83.0},
		{"x": -810.0, "z": 470.0, "radius": 70.0, "height": 31.0, "seed": 97.0},
		{"x": 120.0, "z": 820.0, "radius": 46.0, "height": 16.0, "seed": 109.0},
		{"x": -650.0, "z": -40.0, "radius": 88.0, "height": 34.0, "seed": 131.0},
		{"x": 910.0, "z": 560.0, "radius": 61.0, "height": 24.0, "seed": 149.0},
	]


func _add_island(info: Dictionary) -> void:
	var center: Vector3 = Vector3(float(info.get("x", 0.0)), 0.0, float(info.get("z", 0.0)))
	var radius: float = float(info.get("radius", 45.0))
	var height: float = float(info.get("height", 16.0))
	var seed: float = float(info.get("seed", 1.0))
	var mesh := ArrayMesh.new()
	var vertices := PackedVector3Array()
	var normals := PackedVector3Array()
	var uvs := PackedVector2Array()
	var indices := PackedInt32Array()
	for z in range(ISLAND_GRID + 1):
		for x in range(ISLAND_GRID + 1):
			var fx := (float(x) / ISLAND_GRID - 0.5) * radius * 2.35
			var fz := (float(z) / ISLAND_GRID - 0.5) * radius * 2.35
			var d := Vector2(fx, fz).length() / radius
			var core := pow(clampf(1.0 - d, 0.0, 1.0), 2.0)
			var beach_ring := smoothstep(1.04, 0.72, d)
			var ridge_a := sin((fx + seed) * 0.055) * cos((fz - seed) * 0.047)
			var ridge_b := sin((fx * 0.12 - fz * 0.09) + seed * 0.31)
			var ridges := (ridge_a * 0.30 + ridge_b * 0.14) * core
			var detail := sin((fx - fz) * 0.21 + seed) * sin((fx + fz) * 0.13) * 0.07 * core
			var y := (core + ridges + detail) * height
			y = y * beach_ring - smoothstep(0.76, 1.05, d) * 1.6
			if d > 0.70:
				y = minf(y, 1.3 + (1.0 - d) * 2.2)
			vertices.append(center + Vector3(fx, y, fz))
			normals.append(Vector3.UP)
			uvs.append(Vector2(float(x) / ISLAND_GRID, float(z) / ISLAND_GRID))
	for z in range(ISLAND_GRID):
		for x in range(ISLAND_GRID):
			var i := z * (ISLAND_GRID + 1) + x
			indices.append_array([i, i + ISLAND_GRID + 1, i + 1, i + 1, i + ISLAND_GRID + 1, i + ISLAND_GRID + 2])
	var arrays := []
	arrays.resize(Mesh.ARRAY_MAX)
	arrays[Mesh.ARRAY_VERTEX] = vertices
	arrays[Mesh.ARRAY_NORMAL] = _derive_normals(vertices, indices)
	arrays[Mesh.ARRAY_TEX_UV] = uvs
	arrays[Mesh.ARRAY_INDEX] = indices
	mesh.add_surface_from_arrays(Mesh.PRIMITIVE_TRIANGLES, arrays)
	var island := MeshInstance3D.new()
	island.mesh = mesh
	island.material_override = island_material
	island_root.add_child(island)
	_add_palms(center, radius, height, int(seed))


func _derive_normals(vertices: PackedVector3Array, indices: PackedInt32Array) -> PackedVector3Array:
	var normals := PackedVector3Array()
	normals.resize(vertices.size())
	for i in range(0, indices.size(), 3):
		var a := indices[i]
		var b := indices[i + 1]
		var c := indices[i + 2]
		var n := (vertices[b] - vertices[a]).cross(vertices[c] - vertices[a]).normalized()
		normals[a] += n
		normals[b] += n
		normals[c] += n
	for i in range(normals.size()):
		normals[i] = normals[i].normalized()
	return normals


func _add_palms(center: Vector3, radius: float, height: float, seed: int) -> void:
	var trunk_mat := StandardMaterial3D.new()
	trunk_mat.albedo_color = Color(0.36, 0.22, 0.11)
	trunk_mat.roughness = 0.9
	var leaf_mat := StandardMaterial3D.new()
	leaf_mat.albedo_color = Color(0.06, 0.38, 0.14)
	leaf_mat.roughness = 0.8
	for i in range(8):
		var a := float(seed * 37 + i * 53)
		var r := radius * (0.24 + absf(sin(a * 1.71)) * 0.42)
		var angle := a * 0.37
		var pos := center + Vector3(cos(angle) * r, height * 0.34, sin(angle) * r)
		var trunk := MeshInstance3D.new()
		trunk.mesh = CylinderMesh.new()
		(trunk.mesh as CylinderMesh).top_radius = 0.22
		(trunk.mesh as CylinderMesh).bottom_radius = 0.34
		(trunk.mesh as CylinderMesh).height = 6.5
		trunk.position = pos + Vector3(0, 3.0, 0)
		trunk.rotation.z = sin(a) * 0.18
		trunk.material_override = trunk_mat
		island_root.add_child(trunk)
		for k in range(7):
			var leaf := MeshInstance3D.new()
			var plane := PlaneMesh.new()
			plane.size = Vector2(1.7, 6.4)
			leaf.mesh = plane
			leaf.position = pos + Vector3(0, 6.8, 0)
			leaf.rotation = Vector3(deg_to_rad(68.0), angle + TAU * float(k) / 7.0, 0.0)
			leaf.material_override = leaf_mat
			island_root.add_child(leaf)


func _create_markers() -> void:
	marker_root = Node3D.new()
	marker_root.name = "Markers"
	add_child(marker_root)
	var markers: Array = _bridge_array("get_markers", [760.0])
	if markers.is_empty():
		markers = [
			{"x": -90.0, "z": -155.0, "type": 1.0, "size": 2.6},
			{"x": 285.0, "z": 70.0, "type": 2.0, "size": 4.5},
			{"x": -390.0, "z": 280.0, "type": 3.0, "size": 7.0},
		]
	for marker in markers:
		if marker is Dictionary:
			_add_marker(marker)


func _add_marker(info: Dictionary) -> void:
	var type: int = int(info.get("type", 0))
	var size: float = float(info.get("size", 2.0))
	var x: float = float(info.get("x", 0.0))
	var z: float = float(info.get("z", 0.0))
	var mat := StandardMaterial3D.new()
	mat.albedo_color = Color(0.85, 0.12, 0.08) if type != 2 else Color(0.1, 0.25, 0.95)
	mat.emission_enabled = type == 3
	mat.emission = Color(1.0, 0.75, 0.32)
	mat.emission_energy_multiplier = 1.4
	var node := MeshInstance3D.new()
	node.mesh = CylinderMesh.new()
	(node.mesh as CylinderMesh).top_radius = size * 0.22
	(node.mesh as CylinderMesh).bottom_radius = size * 0.35
	(node.mesh as CylinderMesh).height = size * 2.4
	node.position = Vector3(x, size * 1.1, z)
	node.material_override = mat
	marker_root.add_child(node)
	if type == 3:
		var light := OmniLight3D.new()
		light.light_color = Color(1.0, 0.62, 0.26)
		light.light_energy = 1.8
		light.omni_range = 34.0
		node.add_child(light)


func _create_boat() -> void:
	boat = Node3D.new()
	boat.name = "Sailboat"
	add_child(boat)
	var hull_mat := StandardMaterial3D.new()
	hull_mat.albedo_color = Color(0.34, 0.14, 0.055)
	hull_mat.roughness = 0.72
	var trim_mat := StandardMaterial3D.new()
	trim_mat.albedo_color = Color(0.72, 0.54, 0.30)
	trim_mat.roughness = 0.62
	var hull := MeshInstance3D.new()
	var hull_mesh := BoxMesh.new()
	hull_mesh.size = Vector3(3.2, 0.9, 8.0)
	hull.mesh = hull_mesh
	hull.position.y = 0.48
	hull.scale = Vector3(1.0, 0.7, 1.0)
	hull.material_override = hull_mat
	boat.add_child(hull)
	var mast := MeshInstance3D.new()
	mast.mesh = CylinderMesh.new()
	(mast.mesh as CylinderMesh).top_radius = 0.08
	(mast.mesh as CylinderMesh).bottom_radius = 0.12
	(mast.mesh as CylinderMesh).height = 8.0
	mast.position = Vector3(0.0, 4.1, -0.7)
	mast.material_override = trim_mat
	boat.add_child(mast)
	_add_sail(Vector3(0.08, 4.8, -0.8), 3.2, 6.0, false)
	_add_sail(Vector3(-0.08, 3.5, -0.35), 2.2, 4.0, true)
	var lamp := OmniLight3D.new()
	lamp.name = "WarmLantern"
	lamp.position = Vector3(0.0, 1.35, -3.4)
	lamp.light_color = Color(1.0, 0.58, 0.24)
	lamp.light_energy = 1.2
	lamp.omni_range = 18.0
	boat.add_child(lamp)


func _add_sail(offset: Vector3, width: float, height: float, flip: bool) -> void:
	var mesh := ArrayMesh.new()
	var vertices := PackedVector3Array([
		Vector3(0, -height * 0.5, 0),
		Vector3(width * ( -1.0 if flip else 1.0), -height * 0.35, 0),
		Vector3(0, height * 0.5, 0),
	])
	var arrays := []
	arrays.resize(Mesh.ARRAY_MAX)
	arrays[Mesh.ARRAY_VERTEX] = vertices
	arrays[Mesh.ARRAY_NORMAL] = PackedVector3Array([Vector3.BACK, Vector3.BACK, Vector3.BACK])
	arrays[Mesh.ARRAY_INDEX] = PackedInt32Array([0, 1, 2])
	mesh.add_surface_from_arrays(Mesh.PRIMITIVE_TRIANGLES, arrays)
	var sail := MeshInstance3D.new()
	sail.mesh = mesh
	sail.position = offset
	sail.material_override = sail_material
	boat.add_child(sail)


func _update_boat(dt: float) -> void:
	var input: Vector2 = Input.get_vector("move_left", "move_right", "move_forward", "move_back")
	var forward := Vector3(-sin(camera_yaw), 0.0, -cos(camera_yaw))
	var right := Vector3(cos(camera_yaw), 0.0, -sin(camera_yaw))
	var desired := (right * input.x + forward * -input.y)
	if desired.length_squared() > 0.001:
		desired = desired.normalized()
		boat_yaw = lerp_angle(boat_yaw, atan2(desired.x, -desired.z), 1.0 - pow(0.05, dt))
	var wind := Vector3(-0.35, 0.0, -0.94).normalized()
	var sail_power := clampf(0.55 + max(0.0, desired.dot(wind)) * 0.85, 0.35, 1.6)
	var boost: float = 1.55 if Input.is_action_pressed("boost") else 1.0
	boat_velocity += desired * 13.0 * sail_power * boost * dt
	boat_velocity *= pow(0.10, dt)
	boat_position += boat_velocity * dt
	var boat_forward := Vector2(-sin(boat_yaw), -cos(boat_yaw))
	ocean_material.set_shader_parameter("boat_pos", boat_position)
	ocean_material.set_shader_parameter("boat_forward", boat_forward)
	ocean_material.set_shader_parameter("boat_speed", boat_velocity.length())
	var bob := _water_height(boat_position.x, boat_position.z)
	boat.position = Vector3(boat_position.x, bob + 0.52, boat_position.z)
	boat.rotation = Vector3(sin(Time.get_ticks_msec() * 0.0017) * 0.035, boat_yaw, sin(Time.get_ticks_msec() * 0.0011) * 0.055)


func _water_height(x: float, z: float) -> float:
	var t: float = Time.get_ticks_msec() * 0.001
	var p := Vector2(x, z)
	return sin(p.dot(Vector2(0.92, 0.28).normalized()) * 0.035 + t * 0.54) * 0.72 + sin(p.dot(Vector2(0.38, 1.0).normalized()) * 0.070 + t * 0.86) * 0.38 + sin(p.dot(Vector2(-0.68, 0.55).normalized()) * 0.155 + t * 1.58) * 0.16


func _update_weather(dt: float) -> void:
	day_time = fmod(day_time + dt / 180.0, 1.0)
	next_weather_change -= dt
	if next_weather_change <= 0.0 and target_weather == weather:
		target_weather = _choose_next_weather(weather)
		weather_mix = 0.0
		next_weather_change = 20.0 + randf() * 22.0
	if target_weather != weather:
		weather_mix = minf(1.0, weather_mix + dt / 9.0)
		if weather_mix >= 1.0:
			weather = target_weather
			weather_mix = 0.0
	var base_rain := 1.0 if weather == WEATHER_RAIN else 0.0
	if target_weather == WEATHER_RAIN:
		rain_amount = maxf(base_rain, weather_mix)
	elif weather == WEATHER_RAIN:
		rain_amount = 1.0 - weather_mix
	else:
		rain_amount = 0.0


func _choose_next_weather(current: int) -> int:
	var roll := randf()
	if bridge != null and bridge.has_method("choose_next_weather"):
		return int(bridge.call("choose_next_weather", current, roll))
	if current == WEATHER_SUNNY:
		return WEATHER_CLOUDY if roll < 0.58 else WEATHER_RAIN
	if current == WEATHER_CLOUDY:
		return WEATHER_SUNNY if roll < 0.42 else WEATHER_RAIN
	return WEATHER_CLOUDY if roll < 0.58 else WEATHER_SUNNY


func _update_environment(_dt: float) -> void:
	var sun_angle := day_time * TAU - PI * 0.5
	var sun_dir := Vector3(cos(sun_angle) * -0.52, sin(sun_angle), -0.62).normalized()
	sun.rotation = Basis.looking_at(-sun_dir, Vector3.UP).get_euler()
	var daylight := smoothstep(-0.12, 0.28, sun_dir.y)
	var storm := clampf((0.55 if target_weather == WEATHER_CLOUDY else 0.0) + rain_amount, 0.0, 1.0)
	sun.light_energy = lerpf(0.04, 3.4, daylight) * (1.0 - storm * 0.58)
	moon.light_energy = (1.0 - daylight) * 0.48
	ocean_material.set_shader_parameter("storm", storm)
	ocean_material.set_shader_parameter("rain", rain_amount)
	ocean_material.set_shader_parameter("sun_dir", sun_dir)
	sky_material.set_shader_parameter("day_time", day_time)
	sky_material.set_shader_parameter("storm", storm)
	sky_material.set_shader_parameter("sun_dir", sun_dir)
	for material in cloud_materials:
		material.set_shader_parameter("storm", storm)
		material.set_shader_parameter("coverage", lerpf(0.46, 0.28, rain_amount))
	rain.emitting = rain_amount > 0.05
	rain.amount_ratio = rain_amount
	var env: Environment = world_environment.environment
	env.fog_density = lerpf(0.004, 0.018, storm)
	env.volumetric_fog_density = lerpf(0.012, 0.045, storm)


func _update_camera(dt: float) -> void:
	var distance := lerpf(26.0, 34.0, clampf(boat_velocity.length() / 16.0, 0.0, 1.0))
	var camera_offset := Basis(Vector3.UP, camera_yaw) * Vector3(0.0, 8.8, distance)
	var target_height := maxf(2.2, 5.0 + sin(camera_pitch) * 10.0)
	var target := boat_position + Vector3(0.0, target_height, 0.0)
	camera_rig.position = camera_rig.position.lerp(target, 1.0 - pow(0.01, dt))
	camera.position = camera.position.lerp(camera_offset, 1.0 - pow(0.02, dt))
	camera.look_at(target, Vector3.UP)


func _bridge_array(method: StringName, args: Array) -> Array:
	if bridge == null or not bridge.has_method(method):
		return []
	var value = bridge.callv(method, args)
	return value if value is Array else []


func _ensure_input_map() -> void:
	_add_key_action("move_forward", [KEY_W, KEY_UP])
	_add_key_action("move_back", [KEY_S, KEY_DOWN])
	_add_key_action("move_left", [KEY_A, KEY_LEFT])
	_add_key_action("move_right", [KEY_D, KEY_RIGHT])
	_add_key_action("toggle_mouse", [KEY_ESCAPE])
	_add_key_action("boost", [KEY_SHIFT])


func _add_key_action(action: StringName, keys: Array) -> void:
	if not InputMap.has_action(action):
		InputMap.add_action(action)
	for key in keys:
		var exists := false
		for event in InputMap.action_get_events(action):
			if event is InputEventKey and event.keycode == key:
				exists = true
				break
		if exists:
			continue
		var input := InputEventKey.new()
		input.keycode = key
		input.physical_keycode = key
		InputMap.action_add_event(action, input)
