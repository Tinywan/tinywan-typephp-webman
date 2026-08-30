extends CharacterBody3D

@export var world_path: NodePath
@export var speed := 4.8
@export var jump_velocity := 5.4
@export var mouse_sensitivity := 0.0022
@export var spawn_position := Vector3(5.4, 6.0, 4.2)
@export var spawn_yaw_degrees := 90.0

var gravity := ProjectSettings.get_setting("physics/3d/default_gravity") as float
var yaw := 0.0
var pitch := 0.0
var world: Node
@onready var pivot: Node3D = $CameraPivot
@onready var camera: Camera3D = $CameraPivot/Camera3D


func _ready() -> void:
	_ensure_input_map()
	position = spawn_position
	yaw = deg_to_rad(spawn_yaw_degrees)
	rotation.y = yaw
	world = get_node(world_path)
	Input.mouse_mode = Input.MOUSE_MODE_CAPTURED


func _unhandled_input(event: InputEvent) -> void:
	if event.is_action_pressed("toggle_mouse"):
		Input.mouse_mode = Input.MOUSE_MODE_VISIBLE if Input.mouse_mode == Input.MOUSE_MODE_CAPTURED else Input.MOUSE_MODE_CAPTURED
		get_viewport().set_input_as_handled()
		return

	if event is InputEventMouseMotion and Input.mouse_mode == Input.MOUSE_MODE_CAPTURED:
		yaw -= event.relative.x * mouse_sensitivity
		pitch = clampf(pitch - event.relative.y * mouse_sensitivity, deg_to_rad(-84), deg_to_rad(84))
		rotation.y = yaw
		pivot.rotation.x = pitch
		get_viewport().set_input_as_handled()
		return

	if event.is_action_pressed("break_block"):
		world.break_from_camera(camera)
		get_viewport().set_input_as_handled()
	elif event.is_action_pressed("place_block"):
		world.place_from_camera(camera)
		get_viewport().set_input_as_handled()


func _physics_process(delta: float) -> void:
	if not is_on_floor():
		velocity.y -= gravity * delta

	if Input.is_action_just_pressed("jump") and is_on_floor():
		velocity.y = jump_velocity

	var input_dir := Input.get_vector("move_left", "move_right", "move_forward", "move_back")
	var direction := (transform.basis * Vector3(input_dir.x, 0, input_dir.y)).normalized()
	if direction.length_squared() > 0.0001:
		velocity.x = direction.x * speed
		velocity.z = direction.z * speed
	else:
		velocity.x = move_toward(velocity.x, 0, speed)
		velocity.z = move_toward(velocity.z, 0, speed)

	move_and_slide()


func _ensure_input_map() -> void:
	_add_key_action("move_forward", [KEY_W, KEY_UP])
	_add_key_action("move_back", [KEY_S, KEY_DOWN])
	_add_key_action("move_left", [KEY_A, KEY_LEFT])
	_add_key_action("move_right", [KEY_D, KEY_RIGHT])
	_add_key_action("jump", [KEY_SPACE])
	_add_key_action("toggle_mouse", [KEY_ESCAPE])
	_add_mouse_action("break_block", MOUSE_BUTTON_LEFT)
	_add_mouse_action("place_block", MOUSE_BUTTON_RIGHT)


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


func _add_mouse_action(action: StringName, button: MouseButton) -> void:
	if not InputMap.has_action(action):
		InputMap.add_action(action)
	for event in InputMap.action_get_events(action):
		if event is InputEventMouseButton and event.button_index == button:
			return
	var input := InputEventMouseButton.new()
	input.button_index = button
	InputMap.action_add_event(action, input)
