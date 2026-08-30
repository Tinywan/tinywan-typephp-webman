cmake_minimum_required(VERSION 3.21)
project(typephp_godot_bridge LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

find_package(unofficial-godot-cpp CONFIG REQUIRED)

add_library(typephp_godot_bridge SHARED
    typephp_bridge.cpp
    register_types.cpp
)

target_link_libraries(typephp_godot_bridge PRIVATE unofficial::godot::cpp)

if (MSVC)
    target_compile_options(typephp_godot_bridge PRIVATE /EHsc /bigobj)
endif()

set_target_properties(typephp_godot_bridge PROPERTIES
    RUNTIME_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/../bin"
    LIBRARY_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/../bin"
    ARCHIVE_OUTPUT_DIRECTORY "${CMAKE_CURRENT_SOURCE_DIR}/../bin"
)
