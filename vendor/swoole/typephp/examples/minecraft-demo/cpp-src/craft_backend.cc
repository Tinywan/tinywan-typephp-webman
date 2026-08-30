#include <phpx.h>

#include <windows.h>
#include <gl/GL.h>
#include <algorithm>
#include <cmath>
#include <cstdint>
#include <cstdio>
#include <cstring>
#include <cwchar>
#include <string>
#include <unordered_map>
#include <vector>
#include <windowsx.h>

extern "C" unsigned lodepng_decode32_file(unsigned char **out, unsigned *w, unsigned *h, const char *filename);
extern "C" const char *lodepng_error_text(unsigned code);

using namespace php;

struct BlockKey {
    int x;
    int y;
    int z;

    bool operator==(const BlockKey &other) const {
        return x == other.x && y == other.y && z == other.z;
    }
};

struct BlockKeyHash {
    size_t operator()(const BlockKey &k) const {
        const uint64_t x = static_cast<uint32_t>(k.x);
        const uint64_t y = static_cast<uint32_t>(k.y);
        const uint64_t z = static_cast<uint32_t>(k.z);
        return static_cast<size_t>((x * 73856093u) ^ (y * 19349663u) ^ (z * 83492791u));
    }
};

struct ChunkKey {
    int x;
    int z;

    bool operator==(const ChunkKey &other) const {
        return x == other.x && z == other.z;
    }
};

struct ChunkKeyHash {
    size_t operator()(const ChunkKey &k) const {
        const uint64_t x = static_cast<uint32_t>(k.x);
        const uint64_t z = static_cast<uint32_t>(k.z);
        return static_cast<size_t>((x * 73856093u) ^ (z * 83492791u));
    }
};

struct Vertex {
    float x, y, z;
    float nx, ny, nz;
    float u, v;
    float shade;
};

struct Face {
    Vertex v[4];
    bool water;
    bool cloud;
};

struct Chunk {
    std::unordered_map<BlockKey, int, BlockKeyHash> blocks;
    std::vector<Face> faces;
    int min_y = 0;
    int max_y = 0;
    GLuint opaque_list = 0;
    GLuint transparent_list = 0;
};

static HWND g_hwnd = nullptr;
static HDC g_hdc = nullptr;
static HGLRC g_glrc = nullptr;
static bool g_should_close = false;
static bool g_keys[256] = {};
static bool g_mouse_captured = false;
static bool g_mouse_ready = false;
static POINT g_last_mouse = {};
static double g_mouse_dx = 0.0;
static double g_mouse_dy = 0.0;
static GLuint g_atlas_texture = 0;
static GLuint g_sky_texture = 0;
static int g_width = 1280;
static int g_height = 720;
static float g_camera_x = 8.0f;
static float g_camera_y = 9.0f;
static float g_camera_z = 18.0f;
static float g_camera_yaw = -2.35f;
static float g_camera_pitch = -0.25f;
static std::unordered_map<BlockKey, int, BlockKeyHash> g_blocks;
static std::vector<Face> g_faces;
static std::unordered_map<ChunkKey, Chunk, ChunkKeyHash> g_chunks;
static std::unordered_map<BlockKey, int, BlockKeyHash> g_pending_chunk_blocks;
static ChunkKey g_pending_chunk_key{0, 0};
static bool g_has_pending_chunk = false;

static const int BLOCK_WATER = 64;
static const int BLOCK_CLOUD = 16;
static const int CHUNK_SIZE = 16;

static void delete_chunk_lists(Chunk &chunk) {
    if (chunk.opaque_list) {
        glDeleteLists(chunk.opaque_list, 1);
        chunk.opaque_list = 0;
    }
    if (chunk.transparent_list) {
        glDeleteLists(chunk.transparent_list, 1);
        chunk.transparent_list = 0;
    }
}

static void update_cursor_clip() {
    if (!g_hwnd) {
        return;
    }
    RECT rect{};
    GetClientRect(g_hwnd, &rect);
    POINT top_left{rect.left, rect.top};
    POINT bottom_right{rect.right, rect.bottom};
    ClientToScreen(g_hwnd, &top_left);
    ClientToScreen(g_hwnd, &bottom_right);
    rect.left = top_left.x;
    rect.top = top_left.y;
    rect.right = bottom_right.x;
    rect.bottom = bottom_right.y;
    ClipCursor(&rect);
}

static void enable_mouse_capture() {
    if (!g_hwnd) {
        return;
    }
    if (g_mouse_captured) {
        update_cursor_clip();
        return;
    }
    SetCapture(g_hwnd);
    ShowCursor(FALSE);
    update_cursor_clip();
    g_mouse_captured = true;
}

static void disable_mouse_capture() {
    if (!g_mouse_captured) {
        return;
    }
    ClipCursor(nullptr);
    ReleaseCapture();
    ShowCursor(TRUE);
    g_mouse_captured = false;
}

static const int BLOCK_TILES[65][6] = {
    {0, 0, 0, 0, 0, 0},
    {16, 16, 32, 0, 16, 16}, // grass
    {1, 1, 1, 1, 1, 1},       // sand
    {2, 2, 2, 2, 2, 2},       // stone
    {3, 3, 3, 3, 3, 3},
    {20, 20, 36, 4, 20, 20},  // wood
    {5, 5, 5, 5, 5, 5},
    {6, 6, 6, 6, 6, 6},       // dirt
    {7, 7, 7, 7, 7, 7},
    {24, 24, 40, 8, 24, 24},  // snow
    {9, 9, 9, 9, 9, 9},
    {10, 10, 10, 10, 10, 10}, // cobble
    {11, 11, 11, 11, 11, 11},
    {12, 12, 12, 12, 12, 12},
    {13, 13, 13, 13, 13, 13},
    {14, 14, 14, 14, 14, 14}, // leaves
    {15, 15, 15, 15, 15, 15}, // cloud
};

static const int PLANT_TILES[24] = {
    0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    48, // tall grass
    49, // yellow flower
    50, // red flower
    51, // purple flower
    52, // sun flower
    53, // white flower
    54, // blue flower
};

static LRESULT CALLBACK wnd_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
    case WM_CLOSE:
    case WM_DESTROY:
        g_should_close = true;
        PostQuitMessage(0);
        return 0;
    case WM_SIZE:
        g_width = LOWORD(lp);
        g_height = std::max(1, static_cast<int>(HIWORD(lp)));
        update_cursor_clip();
        return 0;
    case WM_ACTIVATE:
        if (LOWORD(wp) == WA_INACTIVE) {
            disable_mouse_capture();
        } else {
            enable_mouse_capture();
        }
        return 0;
    case WM_KEYDOWN:
        if (wp < 256) {
            g_keys[wp] = true;
        }
        return 0;
    case WM_KEYUP:
        if (wp < 256) {
            g_keys[wp] = false;
        }
        return 0;
    case WM_MOUSEMOVE: {
        POINT p{GET_X_LPARAM(lp), GET_Y_LPARAM(lp)};
        if (g_mouse_ready) {
            g_mouse_dx += static_cast<double>(p.x - g_last_mouse.x);
            g_mouse_dy += static_cast<double>(p.y - g_last_mouse.y);
        }
        g_last_mouse = p;
        g_mouse_ready = true;
        return 0;
    }
    }
    return DefWindowProc(hwnd, msg, wp, lp);
}

static void set_perspective(double fov_y, double aspect, double near_z, double far_z) {
    const double top = near_z * std::tan(fov_y * 3.14159265358979323846 / 360.0);
    const double bottom = -top;
    const double right = top * aspect;
    const double left = -right;
    glFrustum(left, right, bottom, top, near_z, far_z);
}

static void draw_sky_gradient() {
    glMatrixMode(GL_PROJECTION);
    glPushMatrix();
    glLoadIdentity();
    glOrtho(-1, 1, -1, 1, -1, 1);

    glMatrixMode(GL_MODELVIEW);
    glPushMatrix();
    glLoadIdentity();

    glDisable(GL_DEPTH_TEST);
    glDisable(GL_FOG);
    glDisable(GL_CULL_FACE);

    if (g_sky_texture) {
        const float u = std::fmod(g_camera_yaw * -0.08f, 1.0f);
        const float v = std::max(-0.22f, std::min(0.22f, g_camera_pitch * 0.12f));
        glEnable(GL_TEXTURE_2D);
        glBindTexture(GL_TEXTURE_2D, g_sky_texture);
        glColor3f(1.0f, 1.0f, 1.0f);
        glBegin(GL_QUADS);
        glTexCoord2f(u, 0.15f + v);
        glVertex2f(-1.0f, -1.0f);
        glTexCoord2f(u + 1.0f, 0.15f + v);
        glVertex2f(1.0f, -1.0f);
        glTexCoord2f(u + 1.0f, 0.88f + v);
        glVertex2f(1.0f, 1.0f);
        glTexCoord2f(u, 0.88f + v);
        glVertex2f(-1.0f, 1.0f);
        glEnd();
    } else {
        glDisable(GL_TEXTURE_2D);
        glBegin(GL_QUADS);
        glColor3f(0.68f, 0.84f, 0.96f);
        glVertex2f(-1.0f, -1.0f);
        glVertex2f(1.0f, -1.0f);
        glColor3f(0.32f, 0.62f, 0.90f);
        glVertex2f(1.0f, 1.0f);
        glVertex2f(-1.0f, 1.0f);
        glEnd();
    }

    glEnable(GL_CULL_FACE);
    glEnable(GL_FOG);
    glEnable(GL_TEXTURE_2D);
    glEnable(GL_DEPTH_TEST);

    glPopMatrix();
    glMatrixMode(GL_PROJECTION);
    glPopMatrix();
    glMatrixMode(GL_MODELVIEW);
}

static void draw_crosshair() {
    glMatrixMode(GL_PROJECTION);
    glPushMatrix();
    glLoadIdentity();
    glOrtho(-1, 1, -1, 1, -1, 1);

    glMatrixMode(GL_MODELVIEW);
    glPushMatrix();
    glLoadIdentity();

    glDisable(GL_DEPTH_TEST);
    glDisable(GL_TEXTURE_2D);
    glDisable(GL_FOG);
    glDisable(GL_CULL_FACE);
    glDisable(GL_BLEND);

    const float aspect = static_cast<float>(std::max(1, g_height)) / static_cast<float>(std::max(1, g_width));
    const float size_x = 0.018f;
    const float size_y = size_x / std::max(0.1f, aspect);
    const float gap_x = 0.006f;
    const float gap_y = gap_x / std::max(0.1f, aspect);

    glColor3f(0.08f, 0.10f, 0.12f);
    glLineWidth(2.0f);
    glBegin(GL_LINES);
    glVertex2f(-size_x, 0.0f);
    glVertex2f(-gap_x, 0.0f);
    glVertex2f(gap_x, 0.0f);
    glVertex2f(size_x, 0.0f);
    glVertex2f(0.0f, -size_y);
    glVertex2f(0.0f, -gap_y);
    glVertex2f(0.0f, gap_y);
    glVertex2f(0.0f, size_y);
    glEnd();
    glLineWidth(1.0f);

    glEnable(GL_BLEND);
    glEnable(GL_CULL_FACE);
    glEnable(GL_FOG);
    glEnable(GL_TEXTURE_2D);
    glEnable(GL_DEPTH_TEST);

    glPopMatrix();
    glMatrixMode(GL_PROJECTION);
    glPopMatrix();
    glMatrixMode(GL_MODELVIEW);
}

static bool load_texture_into(const char *path, GLuint *texture, bool nearest, bool transparent_magenta) {
    unsigned char *data = nullptr;
    unsigned int width = 0;
    unsigned int height = 0;
    const unsigned int error = lodepng_decode32_file(&data, &width, &height, path);
    if (error != 0) {
        std::fprintf(stderr, "load texture failed: %s: %s\n", path, lodepng_error_text(error));
        return false;
    }

    std::vector<unsigned char> flipped(width * height * 4);
    for (unsigned int y = 0; y < height; y++) {
        const unsigned int src_y = y;
        const unsigned int dst_y = height - y - 1;
        std::memcpy(&flipped[dst_y * width * 4], data + src_y * width * 4, width * 4);
    }
    std::free(data);

    if (transparent_magenta) {
        for (unsigned int i = 0; i < width * height; i++) {
            unsigned char *p = &flipped[i * 4];
            if (p[0] == 255 && p[1] == 0 && p[2] == 255) {
                p[3] = 0;
            }
        }
    }

    if (*texture) {
        glDeleteTextures(1, texture);
        *texture = 0;
    }
    glGenTextures(1, texture);
    glBindTexture(GL_TEXTURE_2D, *texture);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MIN_FILTER, nearest ? GL_NEAREST : GL_LINEAR);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_MAG_FILTER, nearest ? GL_NEAREST : GL_LINEAR);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_S, GL_CLAMP);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_T, GL_CLAMP);
    glTexImage2D(GL_TEXTURE_2D, 0, GL_RGBA, width, height, 0, GL_RGBA, GL_UNSIGNED_BYTE, flipped.data());
    return true;
}

static bool load_texture(const char *path) {
    return load_texture_into(path, &g_atlas_texture, true, true);
}

static bool has_block(int x, int y, int z) {
    return g_blocks.find(BlockKey{x, y, z}) != g_blocks.end();
}

static int get_block(int x, int y, int z) {
    auto it = g_blocks.find(BlockKey{x, y, z});
    return it == g_blocks.end() ? 0 : it->second;
}

static bool is_plant(int type) {
    return type >= 17 && type <= 23;
}

static bool is_transparent_block(int type) {
    return type == 0 || type == BLOCK_WATER || type == BLOCK_CLOUD || is_plant(type) || type == 15;
}

static void tile_uv(int tile, float out[4][2]) {
    const float s = 1.0f / 16.0f;
    const float a = 1.0f / 2048.0f;
    const float b = s - 1.0f / 2048.0f;
    const float du = static_cast<float>(tile % 16) * s;
    const float dv = static_cast<float>(tile / 16) * s;
    out[0][0] = du + a; out[0][1] = dv + b;
    out[1][0] = du + a; out[1][1] = dv + a;
    out[2][0] = du + b; out[2][1] = dv + a;
    out[3][0] = du + b; out[3][1] = dv + b;
}

static void add_face(
    int x, int y, int z, int type, int face_index,
    const float normal[3], const float corners[4][3], bool water)
{
    static const float shades[6] = {0.76f, 0.76f, 1.0f, 0.48f, 0.68f, 0.68f};
    float uv[4][2] = {};
    int tile = 0;
    if (!water && type >= 0 && type < 65) {
        tile = BLOCK_TILES[type][face_index];
    }
    tile_uv(tile, uv);

    Face face{};
    face.water = water;
    face.cloud = type == BLOCK_CLOUD;
    for (int i = 0; i < 4; i++) {
        face.v[i].x = static_cast<float>(x) + corners[i][0];
        face.v[i].y = static_cast<float>(y) + corners[i][1];
        face.v[i].z = static_cast<float>(z) + corners[i][2];
        face.v[i].nx = normal[0];
        face.v[i].ny = normal[1];
        face.v[i].nz = normal[2];
        face.v[i].u = uv[i][0];
        face.v[i].v = uv[i][1];
        face.v[i].shade = water ? 1.0f : shades[face_index];
    }
    g_faces.push_back(face);
}

static void add_plant_face(int x, int y, int z, int type, const float corners[4][3], const float normal[3]) {
    float uv[4][2] = {};
    const int tile = type >= 0 && type < 24 ? PLANT_TILES[type] : 48;
    tile_uv(tile, uv);
    Face face{};
    face.water = false;
    face.cloud = false;
    for (int i = 0; i < 4; i++) {
        face.v[i].x = static_cast<float>(x) + corners[i][0];
        face.v[i].y = static_cast<float>(y) + corners[i][1];
        face.v[i].z = static_cast<float>(z) + corners[i][2];
        face.v[i].nx = normal[0];
        face.v[i].ny = normal[1];
        face.v[i].nz = normal[2];
        face.v[i].u = uv[i][0];
        face.v[i].v = uv[i][1];
        face.v[i].shade = 0.92f;
    }
    g_faces.push_back(face);
}

static void build_plant_faces(int x, int y, int z, int type) {
    const float h = 0.46f;
    const float bottom = -0.5f;
    const float top = 0.55f;
    const float n1[3] = {1, 0, 1};
    const float n2[3] = {1, 0, -1};
    const float a[4][3] = {{-h, bottom, -h}, {-h, top, -h}, {h, top, h}, {h, bottom, h}};
    const float b[4][3] = {{h, bottom, -h}, {h, top, -h}, {-h, top, h}, {-h, bottom, h}};
    const float ar[4][3] = {{h, bottom, h}, {h, top, h}, {-h, top, -h}, {-h, bottom, -h}};
    const float br[4][3] = {{-h, bottom, h}, {-h, top, h}, {h, top, -h}, {h, bottom, -h}};
    add_plant_face(x, y, z, type, a, n1);
    add_plant_face(x, y, z, type, b, n2);
    add_plant_face(x, y, z, type, ar, n1);
    add_plant_face(x, y, z, type, br, n2);
}

static void build_faces_for_block(int x, int y, int z, int type) {
    const float h = 0.5f;
    const float water_y = 0.32f;
    if (is_plant(type)) {
        build_plant_faces(x, y, z, type);
        return;
    }
    if (type == BLOCK_WATER) {
        if (!has_block(x, y + 1, z)) {
            const float normal[3] = {0, 1, 0};
            const float corners[4][3] = {{-h, water_y, -h}, {-h, water_y, h}, {h, water_y, h}, {h, water_y, -h}};
            add_face(x, y, z, type, 2, normal, corners, true);
        }
        return;
    }

    struct Def {
        int dx, dy, dz;
        int face;
        float n[3];
        float c[4][3];
    };
    const Def defs[6] = {
        {-1, 0, 0, 0, {-1, 0, 0}, {{-h, -h, h}, {-h, h, h}, {-h, h, -h}, {-h, -h, -h}}},
        {1, 0, 0, 1, {1, 0, 0}, {{h, -h, -h}, {h, h, -h}, {h, h, h}, {h, -h, h}}},
        {0, 1, 0, 2, {0, 1, 0}, {{-h, h, -h}, {-h, h, h}, {h, h, h}, {h, h, -h}}},
        {0, -1, 0, 3, {0, -1, 0}, {{-h, -h, h}, {-h, -h, -h}, {h, -h, -h}, {h, -h, h}}},
        {0, 0, 1, 4, {0, 0, 1}, {{h, -h, h}, {h, h, h}, {-h, h, h}, {-h, -h, h}}},
        {0, 0, -1, 5, {0, 0, -1}, {{-h, -h, -h}, {-h, h, -h}, {h, h, -h}, {h, -h, -h}}},
    };
    for (const Def &d : defs) {
        const int neighbor = get_block(x + d.dx, y + d.dy, z + d.dz);
        if (is_transparent_block(neighbor)) {
            add_face(x, y, z, type, d.face, d.n, d.c, false);
        }
    }
}

static void draw_face(const Face &face);

static GLuint build_face_list(const std::vector<Face> &faces, bool transparent) {
    GLuint list = glGenLists(1);
    if (!list) {
        return 0;
    }
    glNewList(list, GL_COMPILE);
    for (const Face &face : faces) {
        const bool is_transparent = face.water || face.cloud;
        if (is_transparent == transparent) {
            draw_face(face);
        }
    }
    glEndList();
    return list;
}

static void update_chunk_bounds(Chunk &chunk) {
    if (chunk.blocks.empty()) {
        chunk.min_y = 0;
        chunk.max_y = 0;
        return;
    }
    int min_y = 32767;
    int max_y = -32768;
    for (const auto &entry : chunk.blocks) {
        min_y = std::min(min_y, entry.first.y);
        max_y = std::max(max_y, entry.first.y);
    }
    chunk.min_y = min_y - 1;
    chunk.max_y = max_y + 1;
}

static float chunk_distance_sq(const ChunkKey &key) {
    const float center_x = static_cast<float>(key.x * CHUNK_SIZE) + CHUNK_SIZE * 0.5f;
    const float center_z = static_cast<float>(key.z * CHUNK_SIZE) + CHUNK_SIZE * 0.5f;
    const float dx = center_x - g_camera_x;
    const float dz = center_z - g_camera_z;
    return dx * dx + dz * dz;
}

static bool chunk_visible_from_camera(const ChunkKey &key, const Chunk &chunk) {
    if (chunk.faces.empty()) {
        return false;
    }

    const float center_x = static_cast<float>(key.x * CHUNK_SIZE) + CHUNK_SIZE * 0.5f;
    const float center_y = static_cast<float>(chunk.min_y + chunk.max_y) * 0.5f;
    const float center_z = static_cast<float>(key.z * CHUNK_SIZE) + CHUNK_SIZE * 0.5f;
    const float dx = center_x - g_camera_x;
    const float dy = center_y - g_camera_y;
    const float dz = center_z - g_camera_z;
    const float dist_sq = dx * dx + dy * dy + dz * dz;
    const float radius = 28.0f;
    if (dist_sq <= radius * radius) {
        return true;
    }
    if (dist_sq > 156.0f * 156.0f) {
        return false;
    }

    const float forward_x = -std::sin(g_camera_yaw);
    const float forward_y = std::sin(g_camera_pitch) * 0.65f;
    const float forward_z = -std::cos(g_camera_yaw);
    const float dot = dx * forward_x + dy * forward_y + dz * forward_z;
    if (dot < -radius) {
        return false;
    }

    const float horizontal_dist_sq = dx * dx + dz * dz;
    if (horizontal_dist_sq <= 0.001f) {
        return true;
    }
    const float side_x = std::cos(g_camera_yaw);
    const float side_z = -std::sin(g_camera_yaw);
    const float side = std::fabs(dx * side_x + dz * side_z);
    return side <= std::sqrt(horizontal_dist_sq) * 0.96f + radius;
}

static void rebuild_chunk(int cx, int cz) {
    const ChunkKey key{cx, cz};
    auto it = g_chunks.find(key);
    if (it == g_chunks.end()) {
        return;
    }

    Chunk &chunk = it->second;
    delete_chunk_lists(chunk);
    update_chunk_bounds(chunk);
    chunk.faces.clear();
    chunk.faces.reserve(chunk.blocks.size() * 3);
    const size_t old_count = g_faces.size();
    for (const auto &entry : chunk.blocks) {
        build_faces_for_block(entry.first.x, entry.first.y, entry.first.z, entry.second);
    }
    chunk.faces.assign(g_faces.begin() + old_count, g_faces.end());
    g_faces.resize(old_count);
    chunk.opaque_list = build_face_list(chunk.faces, false);
    chunk.transparent_list = build_face_list(chunk.faces, true);
}

static void rebuild_chunk_and_neighbors(int cx, int cz) {
    rebuild_chunk(cx, cz);
    rebuild_chunk(cx - 1, cz);
    rebuild_chunk(cx + 1, cz);
    rebuild_chunk(cx, cz - 1);
    rebuild_chunk(cx, cz + 1);
}

static void draw_face(const Face &face) {
    if (face.water) {
        glColor4f(0.12f, 0.48f, 0.82f, 0.58f);
        glBegin(GL_QUADS);
        for (const Vertex &v : face.v) {
            glVertex3f(v.x, v.y, v.z);
        }
        glEnd();
        return;
    }
    if (face.cloud) {
        glColor4f(1.0f, 1.0f, 1.0f, 0.72f);
        glBegin(GL_QUADS);
        for (const Vertex &v : face.v) {
            glVertex3f(v.x, v.y, v.z);
        }
        glEnd();
        return;
    }
    glBegin(GL_QUADS);
    for (const Vertex &v : face.v) {
        glColor4f(v.shade, v.shade, v.shade, 1.0f);
        glNormal3f(v.nx, v.ny, v.nz);
        glTexCoord2f(v.u, v.v);
        glVertex3f(v.x, v.y, v.z);
    }
    glEnd();
}

Bool php_craft_init(String title, Int width, Int height, String texture_path) {
    SetProcessDPIAware();
    SetConsoleOutputCP(65001);
    g_width = static_cast<int>(width);
    g_height = static_cast<int>(height);

    WNDCLASS wc{};
    wc.style = CS_OWNDC | CS_HREDRAW | CS_VREDRAW;
    wc.lpfnWndProc = wnd_proc;
    wc.hInstance = GetModuleHandle(nullptr);
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.lpszClassName = "TypePhpCraftWindow";
    RegisterClass(&wc);

    g_hwnd = CreateWindowEx(
        0, wc.lpszClassName, title.data(),
        WS_OVERLAPPEDWINDOW | WS_VISIBLE,
        CW_USEDEFAULT, CW_USEDEFAULT, g_width, g_height,
        nullptr, nullptr, wc.hInstance, nullptr);
    if (!g_hwnd) {
        return false;
    }

    g_hdc = GetDC(g_hwnd);
    PIXELFORMATDESCRIPTOR pfd{};
    pfd.nSize = sizeof(pfd);
    pfd.nVersion = 1;
    pfd.dwFlags = PFD_DRAW_TO_WINDOW | PFD_SUPPORT_OPENGL | PFD_DOUBLEBUFFER;
    pfd.iPixelType = PFD_TYPE_RGBA;
    pfd.cColorBits = 32;
    pfd.cDepthBits = 24;
    pfd.cAlphaBits = 8;
    pfd.iLayerType = PFD_MAIN_PLANE;
    const int pf = ChoosePixelFormat(g_hdc, &pfd);
    SetPixelFormat(g_hdc, pf, &pfd);

    g_glrc = wglCreateContext(g_hdc);
    wglMakeCurrent(g_hdc, g_glrc);

    glEnable(GL_DEPTH_TEST);
    glEnable(GL_CULL_FACE);
    glEnable(GL_TEXTURE_2D);
    glEnable(GL_ALPHA_TEST);
    glAlphaFunc(GL_GREATER, 0.4f);
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glEnable(GL_FOG);
    const GLfloat fog_color[] = {0.68f, 0.84f, 0.96f, 1.0f};
    glFogfv(GL_FOG_COLOR, fog_color);
    glFogi(GL_FOG_MODE, GL_LINEAR);
    glFogf(GL_FOG_START, 72.0f);
    glFogf(GL_FOG_END, 118.0f);
    glClearColor(0.68f, 0.84f, 0.96f, 1.0f);

    enable_mouse_capture();
    return load_texture(texture_path.data());
}

Bool php_craft_set_sky_texture(String texture_path) {
    if (!load_texture_into(texture_path.data(), &g_sky_texture, false, false)) {
        return false;
    }
    glBindTexture(GL_TEXTURE_2D, g_sky_texture);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_S, GL_REPEAT);
    glTexParameteri(GL_TEXTURE_2D, GL_TEXTURE_WRAP_T, GL_CLAMP);
    return true;
}

void php_craft_shutdown() {
    disable_mouse_capture();
    for (auto &entry : g_chunks) {
        delete_chunk_lists(entry.second);
    }
    g_chunks.clear();
    if (g_atlas_texture) {
        glDeleteTextures(1, &g_atlas_texture);
        g_atlas_texture = 0;
    }
    if (g_sky_texture) {
        glDeleteTextures(1, &g_sky_texture);
        g_sky_texture = 0;
    }
    if (g_glrc) {
        wglMakeCurrent(nullptr, nullptr);
        wglDeleteContext(g_glrc);
        g_glrc = nullptr;
    }
    if (g_hwnd && g_hdc) {
        ReleaseDC(g_hwnd, g_hdc);
        g_hdc = nullptr;
    }
    if (g_hwnd) {
        DestroyWindow(g_hwnd);
        g_hwnd = nullptr;
    }
}

Bool php_craft_should_close() {
    return g_should_close;
}

void php_craft_poll_events() {
    MSG msg;
    while (PeekMessage(&msg, nullptr, 0, 0, PM_REMOVE)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
    }
}

void php_craft_begin_world() {
    for (auto &entry : g_chunks) {
        delete_chunk_lists(entry.second);
    }
    g_blocks.clear();
    g_faces.clear();
    g_chunks.clear();
    g_pending_chunk_blocks.clear();
    g_has_pending_chunk = false;
}

void php_craft_set_block(Int x, Int y, Int z, Int type) {
    const int t = static_cast<int>(type);
    if (t != 0) {
        g_blocks[BlockKey{static_cast<int>(x), static_cast<int>(y), static_cast<int>(z)}] = t;
    }
}

void php_craft_build_mesh() {
    g_faces.clear();
    g_faces.reserve(g_blocks.size() * 3);
    for (const auto &entry : g_blocks) {
        build_faces_for_block(entry.first.x, entry.first.y, entry.first.z, entry.second);
    }
    std::printf("Craft mesh: blocks=%zu faces=%zu\n", g_blocks.size(), g_faces.size());
    std::fflush(stdout);
}

void php_craft_begin_chunk(Int chunk_x, Int chunk_z) {
    g_pending_chunk_key = ChunkKey{static_cast<int>(chunk_x), static_cast<int>(chunk_z)};
    g_pending_chunk_blocks.clear();
    g_has_pending_chunk = true;
}

void php_craft_set_chunk_block(Int x, Int y, Int z, Int type) {
    if (!g_has_pending_chunk) {
        return;
    }
    const int t = static_cast<int>(type);
    if (t != 0) {
        g_pending_chunk_blocks[BlockKey{static_cast<int>(x), static_cast<int>(y), static_cast<int>(z)}] = t;
    }
}

void php_craft_commit_chunk(Int chunk_x, Int chunk_z) {
    const int cx = static_cast<int>(chunk_x);
    const int cz = static_cast<int>(chunk_z);
    const ChunkKey key{cx, cz};

    auto old = g_chunks.find(key);
    if (old != g_chunks.end()) {
        for (const auto &entry : old->second.blocks) {
            g_blocks.erase(entry.first);
        }
    }

    Chunk &chunk = g_chunks[key];
    delete_chunk_lists(chunk);
    chunk.blocks = g_pending_chunk_blocks;
    chunk.faces.clear();
    for (const auto &entry : chunk.blocks) {
        g_blocks[entry.first] = entry.second;
    }

    g_pending_chunk_blocks.clear();
    g_has_pending_chunk = false;
    rebuild_chunk_and_neighbors(cx, cz);
}

void php_craft_remove_chunk(Int chunk_x, Int chunk_z) {
    const int cx = static_cast<int>(chunk_x);
    const int cz = static_cast<int>(chunk_z);
    const ChunkKey key{cx, cz};
    auto it = g_chunks.find(key);
    if (it == g_chunks.end()) {
        return;
    }
    for (const auto &entry : it->second.blocks) {
        g_blocks.erase(entry.first);
    }
    delete_chunk_lists(it->second);
    g_chunks.erase(it);
    rebuild_chunk(cx - 1, cz);
    rebuild_chunk(cx + 1, cz);
    rebuild_chunk(cx, cz - 1);
    rebuild_chunk(cx, cz + 1);
}

void php_craft_render_frame() {
    SetWindowTextA(g_hwnd, "TypePHP Minecraft Demo - Craft/OpenGL");
    glViewport(0, 0, g_width, std::max(1, g_height));
    glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    draw_sky_gradient();
    glClear(GL_DEPTH_BUFFER_BIT);

    glMatrixMode(GL_PROJECTION);
    glLoadIdentity();
    set_perspective(70.0, static_cast<double>(g_width) / std::max(1, g_height), 0.1, 220.0);

    glMatrixMode(GL_MODELVIEW);
    glLoadIdentity();
    glRotatef(-g_camera_pitch * 57.2957795f, 1, 0, 0);
    glRotatef(-g_camera_yaw * 57.2957795f, 0, 1, 0);
    glTranslatef(-g_camera_x, -g_camera_y, -g_camera_z);

    struct VisibleChunk {
        const Chunk *chunk;
        float distance_sq;
    };
    std::vector<VisibleChunk> visible_chunks;
    visible_chunks.reserve(g_chunks.size());
    for (const auto &chunk_entry : g_chunks) {
        if (chunk_visible_from_camera(chunk_entry.first, chunk_entry.second)) {
            visible_chunks.push_back(VisibleChunk{&chunk_entry.second, chunk_distance_sq(chunk_entry.first)});
        }
    }

    glBindTexture(GL_TEXTURE_2D, g_atlas_texture);
    glEnable(GL_TEXTURE_2D);
    glDisable(GL_BLEND);
    for (const VisibleChunk &visible : visible_chunks) {
        if (visible.chunk->opaque_list) {
            glCallList(visible.chunk->opaque_list);
        }
    }
    for (const Face &face : g_faces) {
        if (!face.water && !face.cloud) {
            draw_face(face);
        }
    }

    glDisable(GL_TEXTURE_2D);
    glEnable(GL_BLEND);
    glDepthMask(GL_FALSE);
    std::sort(visible_chunks.begin(), visible_chunks.end(), [](const VisibleChunk &a, const VisibleChunk &b) {
        return a.distance_sq > b.distance_sq;
    });
    for (const VisibleChunk &visible : visible_chunks) {
        if (visible.chunk->transparent_list) {
            glCallList(visible.chunk->transparent_list);
        }
    }
    for (const Face &face : g_faces) {
        if (face.water || face.cloud) {
            draw_face(face);
        }
    }
    glDepthMask(GL_TRUE);

    draw_crosshair();
    SwapBuffers(g_hdc);
}

void php_craft_render_loading(Int done, Int total) {
    const int d = std::max(0, static_cast<int>(done));
    const int t = std::max(1, static_cast<int>(total));
    const float progress = std::min(1.0f, static_cast<float>(d) / static_cast<float>(t));

    wchar_t title[128];
    swprintf_s(title, L"\x6b63\x5728\x6e32\x67d3\x4e16\x754c... %d/%d", d, t);
    SetWindowTextW(g_hwnd, title);

    glViewport(0, 0, g_width, std::max(1, g_height));
    glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    draw_sky_gradient();

    glMatrixMode(GL_PROJECTION);
    glPushMatrix();
    glLoadIdentity();
    glOrtho(-1, 1, -1, 1, -1, 1);

    glMatrixMode(GL_MODELVIEW);
    glPushMatrix();
    glLoadIdentity();

    glDisable(GL_DEPTH_TEST);
    glDisable(GL_TEXTURE_2D);
    glDisable(GL_FOG);
    glDisable(GL_CULL_FACE);

    const float left = -0.56f;
    const float right = 0.56f;
    const float bottom = -0.18f;
    const float top = -0.11f;
    const float fill = left + (right - left) * progress;

    glColor4f(0.0f, 0.0f, 0.0f, 0.32f);
    glBegin(GL_QUADS);
    glVertex2f(left - 0.02f, bottom - 0.02f);
    glVertex2f(right + 0.02f, bottom - 0.02f);
    glVertex2f(right + 0.02f, top + 0.02f);
    glVertex2f(left - 0.02f, top + 0.02f);
    glEnd();

    glColor4f(0.92f, 0.96f, 1.0f, 1.0f);
    glBegin(GL_QUADS);
    glVertex2f(left, bottom);
    glVertex2f(fill, bottom);
    glVertex2f(fill, top);
    glVertex2f(left, top);
    glEnd();

    glEnable(GL_CULL_FACE);
    glEnable(GL_FOG);
    glEnable(GL_TEXTURE_2D);
    glEnable(GL_DEPTH_TEST);

    glPopMatrix();
    glMatrixMode(GL_PROJECTION);
    glPopMatrix();
    glMatrixMode(GL_MODELVIEW);

    SwapBuffers(g_hdc);
}

void php_craft_sleep(Int milliseconds) {
    Sleep(static_cast<DWORD>(milliseconds));
}

Bool php_craft_key_pressed(Int key) {
    const int k = static_cast<int>(key);
    return k >= 0 && k < 256 && g_keys[k];
}

Float php_craft_mouse_delta_x() {
    const double value = g_mouse_dx;
    g_mouse_dx = 0.0;
    return value;
}

Float php_craft_mouse_delta_y() {
    const double value = g_mouse_dy;
    g_mouse_dy = 0.0;
    return value;
}

void php_craft_set_camera(Float x, Float y, Float z, Float yaw, Float pitch) {
    g_camera_x = static_cast<float>(x);
    g_camera_y = static_cast<float>(y);
    g_camera_z = static_cast<float>(z);
    g_camera_yaw = static_cast<float>(yaw);
    g_camera_pitch = static_cast<float>(pitch);
}

Float php_craft_get_time() {
    static LARGE_INTEGER freq{};
    static LARGE_INTEGER start{};
    if (freq.QuadPart == 0) {
        QueryPerformanceFrequency(&freq);
        QueryPerformanceCounter(&start);
    }
    LARGE_INTEGER now{};
    QueryPerformanceCounter(&now);
    return static_cast<double>(now.QuadPart - start.QuadPart) / static_cast<double>(freq.QuadPart);
}

Bool php_craft_confirm_exit() {
    disable_mouse_capture();
    const int result = MessageBoxA(
        g_hwnd,
        "Exit this world?",
        "Confirm Exit",
        MB_YESNO | MB_ICONQUESTION | MB_DEFBUTTON2);
    enable_mouse_capture();
    g_mouse_dx = 0.0;
    g_mouse_dy = 0.0;
    g_mouse_ready = false;
    g_keys[VK_ESCAPE] = false;
    return result == IDYES;
}
