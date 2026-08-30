#include <phpx.h>

#include <windows.h>
#include <gl/GL.h>
#include <algorithm>
#include <cmath>
#include <cstdio>
#include <cstdint>
#include <string>
#include <unordered_map>
#include <vector>
#include <windowsx.h>

using namespace php;

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
static int g_width = 1280;
static int g_height = 720;

static float g_boat_x = 0.0f;
static float g_boat_z = 0.0f;
static float g_boat_yaw = 0.0f;
static float g_boat_speed = 0.0f;
static float g_camera_yaw = 0.0f;
static float g_camera_pitch = 0.32f;
static float g_camera_distance = 18.0f;
static float g_day_time = 0.28f;
static int g_weather = 0;
static float g_weather_mix = 0.0f;
static float g_rain = 0.0f;
static double g_start_time = 0.0;

struct ChunkKey {
    int x;
    int z;

    bool operator==(const ChunkKey &other) const {
        return x == other.x && z == other.z;
    }
};

struct ChunkKeyHash {
    size_t operator()(const ChunkKey &key) const {
        const uint64_t x = static_cast<uint32_t>(key.x);
        const uint64_t z = static_cast<uint32_t>(key.z);
        return static_cast<size_t>((x * 73856093u) ^ (z * 83492791u));
    }
};

struct OceanMarker {
    float x;
    float z;
    int type;
    float size;
};

static std::unordered_map<ChunkKey, std::vector<OceanMarker>, ChunkKeyHash> g_marker_chunks;
static std::vector<OceanMarker> g_pending_markers;
static ChunkKey g_pending_chunk{0, 0};
static bool g_has_pending_chunk = false;

struct SpectralWave {
    float dir_x;
    float dir_z;
    float amplitude;
    float wavelength;
    float phase;
    float steepness;
};

static const SpectralWave SPECTRAL_WAVES[] = {
    {0.78f, 0.62f, 0.34f, 28.0f, 0.0f, 0.42f},
    {0.52f, 0.85f, 0.22f, 17.0f, 1.7f, 0.36f},
    {-0.28f, 0.96f, 0.14f, 9.5f, 3.2f, 0.25f},
    {0.98f, -0.20f, 0.08f, 5.8f, 4.6f, 0.16f},
    {-0.76f, 0.65f, 0.06f, 3.4f, 2.4f, 0.11f},
};

static double now_seconds() {
    LARGE_INTEGER freq;
    LARGE_INTEGER counter;
    QueryPerformanceFrequency(&freq);
    QueryPerformanceCounter(&counter);
    return static_cast<double>(counter.QuadPart) / static_cast<double>(freq.QuadPart);
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
    if (!g_hwnd || g_mouse_captured) {
        if (g_mouse_captured) {
            update_cursor_clip();
        }
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
    g_mouse_ready = false;
}

static LRESULT CALLBACK wnd_proc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch (msg) {
    case WM_CLOSE:
    case WM_DESTROY:
        g_should_close = true;
        PostQuitMessage(0);
        return 0;
    case WM_SIZE:
        g_width = std::max(1, static_cast<int>(LOWORD(lp)));
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
    case WM_KEYUP:
        if (wp < 256) {
            g_keys[wp] = false;
        }
        return 0;
    }
    return DefWindowProc(hwnd, msg, wp, lp);
}

static void set_perspective(double fov_y, double aspect, double near_z, double far_z) {
    const double top = near_z * std::tan(fov_y * 3.14159265358979323846 / 360.0);
    const double right = top * aspect;
    glFrustum(-right, right, -top, top, near_z, far_z);
}

static float clamp01(float v) {
    return std::max(0.0f, std::min(1.0f, v));
}

static float smoothstep(float edge0, float edge1, float x) {
    const float t = clamp01((x - edge0) / (edge1 - edge0));
    return t * t * (3.0f - 2.0f * t);
}

static float active_weather_strength() {
    const float base = g_weather == 1 ? 0.55f : (g_weather == 2 ? 1.0f : 0.0f);
    const float transition = g_weather_mix * 0.30f;
    return clamp01(std::max(base, transition));
}

static float wave_height(float x, float z, float t) {
    float height = 0.0f;
    const float storm = active_weather_strength();
    for (const SpectralWave &wave : SPECTRAL_WAVES) {
        const float k = 6.2831853f / wave.wavelength;
        const float omega = std::sqrt(9.81f * k);
        const float phase = k * (x * wave.dir_x + z * wave.dir_z) + omega * t + wave.phase;
        const float choppy = std::sin(phase) + 0.34f * std::sin(phase * 2.07f + wave.phase);
        height += choppy * wave.amplitude * (1.0f + storm * wave.steepness);
    }
    return height;
}

static void normalize3(float v[3]) {
    const float len = std::sqrt(v[0] * v[0] + v[1] * v[1] + v[2] * v[2]);
    if (len > 0.0001f) {
        v[0] /= len;
        v[1] /= len;
        v[2] /= len;
    }
}

static void wave_normal(float x, float z, float t, float out[3]) {
    const float e = 0.45f;
    const float hx1 = wave_height(x + e, z, t);
    const float hx0 = wave_height(x - e, z, t);
    const float hz1 = wave_height(x, z + e, t);
    const float hz0 = wave_height(x, z - e, t);
    out[0] = -(hx1 - hx0) / (e * 2.0f);
    out[1] = 1.0f;
    out[2] = -(hz1 - hz0) / (e * 2.0f);
    normalize3(out);
}

static float sun_height() {
    return std::sin((g_day_time - 0.25f) * 6.2831853f);
}

static void sun_direction(float out[3]) {
    const float a = (g_day_time - 0.25f) * 6.2831853f;
    out[0] = std::cos(a) * 0.45f;
    out[1] = std::max(0.14f, std::sin(a));
    out[2] = -0.72f;
    normalize3(out);
}

static float fresnel_schlick(float cos_theta) {
    const float f0 = 0.02f;
    return f0 + (1.0f - f0) * std::pow(clamp01(1.0f - cos_theta), 5.0f);
}

static float ggx_distribution(float n_dot_h, float roughness) {
    const float a = roughness * roughness;
    const float a2 = a * a;
    const float d = n_dot_h * n_dot_h * (a2 - 1.0f) + 1.0f;
    return a2 / std::max(0.0001f, 3.14159265f * d * d);
}

static float slope_roughness(const float n[3]) {
    const float slope = std::sqrt(n[0] * n[0] + n[2] * n[2]);
    return std::max(0.05f, std::min(0.92f, 0.18f + slope * 1.9f + g_rain * 0.20f));
}

static float slope_ambient_occlusion(const float n[3]) {
    const float slope = std::sqrt(n[0] * n[0] + n[2] * n[2]);
    return clamp01(1.0f - 3.8f * slope);
}

static float exp_fog_factor(float distance, float density) {
    const float f = std::exp(-std::pow(density * distance, 2.0f));
    return clamp01(f);
}

static float night_factor() {
    return clamp01(1.0f - smoothstep(0.18f, 0.30f, g_day_time) +
        smoothstep(0.72f, 0.90f, g_day_time));
}

static float dawn_dusk_factor() {
    const float dawn = smoothstep(0.18f, 0.30f, g_day_time) * (1.0f - smoothstep(0.35f, 0.46f, g_day_time));
    const float dusk = smoothstep(0.60f, 0.72f, g_day_time) * (1.0f - smoothstep(0.78f, 0.90f, g_day_time));
    return std::max(dawn, dusk);
}

static float tone_map(float color) {
    color = std::max(0.0f, color);
    color = color / (1.0f + color);
    return std::pow(color, 1.0f / 2.2f);
}

static void tone_map_rgb(float &r, float &g, float &b, float exposure) {
    r = tone_map(r * exposure);
    g = tone_map(g * exposure);
    b = tone_map(b * exposure);
}

static float remap_value(float value, float old_min, float old_max, float new_min, float new_max) {
    return new_min + ((value - old_min) / (old_max - old_min)) * (new_max - new_min);
}

static float hash31(float x, float y, float z) {
    const float n = std::sin(x * 127.1f + y * 311.7f + z * 74.7f) * 43758.5453f;
    return n - std::floor(n);
}

static float value_noise3(float x, float y, float z) {
    const float ix = std::floor(x);
    const float iy = std::floor(y);
    const float iz = std::floor(z);
    const float fx = x - ix;
    const float fy = y - iy;
    const float fz = z - iz;
    const float ux = fx * fx * fx * (fx * (fx * 6.0f - 15.0f) + 10.0f);
    const float uy = fy * fy * fy * (fy * (fy * 6.0f - 15.0f) + 10.0f);
    const float uz = fz * fz * fz * (fz * (fz * 6.0f - 15.0f) + 10.0f);

    float c[2][2][2];
    for (int dx = 0; dx <= 1; dx++) {
        for (int dy = 0; dy <= 1; dy++) {
            for (int dz = 0; dz <= 1; dz++) {
                c[dx][dy][dz] = hash31(ix + dx, iy + dy, iz + dz);
            }
        }
    }

    const float x00 = c[0][0][0] + (c[1][0][0] - c[0][0][0]) * ux;
    const float x10 = c[0][1][0] + (c[1][1][0] - c[0][1][0]) * ux;
    const float x01 = c[0][0][1] + (c[1][0][1] - c[0][0][1]) * ux;
    const float x11 = c[0][1][1] + (c[1][1][1] - c[0][1][1]) * ux;
    const float y0 = x00 + (x10 - x00) * uy;
    const float y1 = x01 + (x11 - x01) * uy;
    return y0 + (y1 - y0) * uz;
}

static float worley_noise2(float x, float y) {
    const int ix = static_cast<int>(std::floor(x));
    const int iy = static_cast<int>(std::floor(y));
    float min_dist = 10000.0f;
    for (int ox = -1; ox <= 1; ox++) {
        for (int oy = -1; oy <= 1; oy++) {
            const float cx = static_cast<float>(ix + ox) + hash31(static_cast<float>(ix + ox), static_cast<float>(iy + oy), 11.0f);
            const float cy = static_cast<float>(iy + oy) + hash31(static_cast<float>(ix + ox), static_cast<float>(iy + oy), 29.0f);
            const float dx = x - cx;
            const float dy = y - cy;
            min_dist = std::min(min_dist, dx * dx + dy * dy);
        }
    }
    return clamp01(1.0f - min_dist);
}

static float cloud_density_sample(float x, float y, float time, float coverage) {
    const float height_fraction = clamp01((y + 0.20f) / 0.82f);
    const float wind_x = std::cos(g_day_time * 6.2831853f + 0.7f);
    const float wind_y = std::sin(g_day_time * 6.2831853f + 0.7f);
    const float shear = height_fraction * 0.34f;
    x += wind_x * time * 0.012f + shear;
    y += wind_y * time * 0.008f;

    const float base = value_noise3(x * 2.2f, y * 3.0f, time * 0.015f);
    const float worley = worley_noise2(x * 3.4f, y * 2.1f) * 0.625f +
        worley_noise2(x * 6.8f + 17.0f, y * 4.2f - 9.0f) * 0.25f +
        worley_noise2(x * 13.6f - 5.0f, y * 8.4f + 23.0f) * 0.125f;
    float shape = remap_value(base, 1.0f - worley * 0.78f, 1.0f, 0.0f, 1.0f);
    const float vertical = smoothstep(0.0f, 0.16f, height_fraction) * (1.0f - smoothstep(0.78f, 1.0f, height_fraction));
    shape *= vertical;

    const float detail = value_noise3(x * 13.0f + 4.0f, y * 16.0f - 2.0f, time * 0.05f);
    shape = remap_value(shape, (1.0f - detail) * 0.34f, 1.0f, 0.0f, 1.0f);
    shape = remap_value(shape, coverage, 1.0f, 0.0f, 1.0f) * coverage;
    return clamp01(shape);
}

static float henyey_greenstein_phase(float cos_angle, float g) {
    const float g2 = g * g;
    return ((1.0f - g2) / std::pow(1.0f + g2 - 2.0f * g * cos_angle, 1.5f)) * 0.07957747f;
}

static float cloud_powder(float density, float cos_angle) {
    const float powder = 1.0f - std::exp(-density * 2.0f);
    const float edge = clamp01((-cos_angle * 0.5f) + 0.5f);
    return 1.0f + (powder - 1.0f) * edge;
}

static void sky_colors(float top[3], float horizon[3], float *light) {
    const float night = 1.0f - smoothstep(0.18f, 0.27f, g_day_time) +
        smoothstep(0.78f, 0.92f, g_day_time);
    const float dawn = smoothstep(0.18f, 0.30f, g_day_time) * (1.0f - smoothstep(0.34f, 0.43f, g_day_time));
    const float dusk = smoothstep(0.62f, 0.72f, g_day_time) * (1.0f - smoothstep(0.78f, 0.88f, g_day_time));
    const float golden = std::max(dawn, dusk);
    const float cloudy = g_weather == 1 ? 0.72f : (g_weather == 2 ? 0.90f : 0.0f);

    top[0] = 0.12f + 0.28f * (1.0f - night) + 0.20f * golden;
    top[1] = 0.18f + 0.42f * (1.0f - night) + 0.10f * golden;
    top[2] = 0.34f + 0.54f * (1.0f - night) - 0.10f * golden;
    horizon[0] = 0.18f + 0.55f * (1.0f - night) + 0.34f * golden;
    horizon[1] = 0.23f + 0.47f * (1.0f - night) + 0.17f * golden;
    horizon[2] = 0.34f + 0.44f * (1.0f - night) - 0.22f * golden;

    for (int i = 0; i < 3; i++) {
        top[i] = top[i] * (1.0f - cloudy) + 0.33f * cloudy;
        horizon[i] = horizon[i] * (1.0f - cloudy) + 0.46f * cloudy;
    }
    const float exposure = 1.05f + dawn_dusk_factor() * 0.25f - cloudy * 0.10f;
    tone_map_rgb(top[0], top[1], top[2], exposure);
    tone_map_rgb(horizon[0], horizon[1], horizon[2], exposure);
    *light = std::max(0.12f, 1.0f - night * 0.74f - cloudy * 0.28f);
}

static void draw_sky() {
    float top[3];
    float horizon[3];
    float light = 1.0f;
    sky_colors(top, horizon, &light);

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

    glBegin(GL_QUADS);
    glColor3f(horizon[0], horizon[1], horizon[2]);
    glVertex2f(-1.0f, -1.0f);
    glVertex2f(1.0f, -1.0f);
    glColor3f(top[0], top[1], top[2]);
    glVertex2f(1.0f, 1.0f);
    glVertex2f(-1.0f, 1.0f);
    glEnd();

    const float sun_angle = (g_day_time - 0.25f) * 6.2831853f;
    const float sx = std::cos(sun_angle) * 0.68f;
    const float sy = std::sin(sun_angle) * 0.74f;
    const bool moon = sy < -0.05f;
    const float body_y = moon ? -sy : sy;
    if (body_y > -0.10f) {
        glColor4f(moon ? 0.74f : 1.0f, moon ? 0.80f : 0.86f, moon ? 0.92f : 0.42f, moon ? 0.85f : 0.95f);
        const float r = moon ? 0.045f : 0.065f;
        glBegin(GL_TRIANGLE_FAN);
        glVertex2f(sx, body_y);
        for (int i = 0; i <= 48; i++) {
            const float a = static_cast<float>(i) / 48.0f * 6.2831853f;
            glVertex2f(sx + std::cos(a) * r, body_y + std::sin(a) * r);
        }
        glEnd();
    }

    const float time = static_cast<float>(now_seconds() - g_start_time);
    const float weather_coverage = g_weather == 0 ? 0.48f : (g_weather == 1 ? 0.34f : 0.24f);
    const float cloud_strength = g_weather == 0 ? 0.46f : (g_weather == 1 ? 0.78f : 0.92f);
    float sun_dir[3];
    sun_direction(sun_dir);
    const float cos_angle = clamp01(sun_dir[1] * 0.45f + 0.35f);
    const float hg = std::max(henyey_greenstein_phase(cos_angle, 0.62f), henyey_greenstein_phase(cos_angle, -0.25f)) * 0.45f + 0.64f;

    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    for (int layer = 0; layer < 5; layer++) {
        const float layer_y = 0.26f + layer * 0.075f;
        const float layer_scale = 1.0f + layer * 0.20f;
        const float alpha_scale = (0.10f + layer * 0.055f) * cloud_strength;
        const float y_step = 0.055f;
        const float x_step = 0.075f;
        for (float cy = -0.12f; cy <= 0.76f; cy += y_step) {
            glBegin(GL_QUAD_STRIP);
            for (float cx = -1.24f; cx <= 1.26f; cx += x_step) {
                for (int row = 0; row < 2; row++) {
                    const float px = cx;
                    const float py = cy + row * y_step;
                    const float density = cloud_density_sample(px * layer_scale + layer * 2.7f, py * layer_scale, time, weather_coverage);
                    if (density <= 0.01f || py < -0.10f) {
                        glColor4f(1.0f, 1.0f, 1.0f, 0.0f);
                    } else {
                        const float height_fraction = clamp01((py + 0.12f) / 0.88f);
                        const float transmittance = std::exp(-density * (1.45f + g_rain * 0.55f));
                        const float powder = cloud_powder(density, cos_angle);
                        const float lit = (0.38f + hg * powder * (0.85f - g_rain * 0.28f)) * (0.72f + height_fraction * 0.35f);
                        float cr = (0.55f + top[0] * 0.48f) * lit;
                        float cg = (0.58f + top[1] * 0.44f) * lit;
                        float cb = (0.62f + top[2] * 0.40f) * lit;
                        cr = cr * (1.0f - g_rain * 0.24f) + 0.34f * g_rain;
                        cg = cg * (1.0f - g_rain * 0.22f) + 0.36f * g_rain;
                        cb = cb * (1.0f - g_rain * 0.18f) + 0.40f * g_rain;
                        tone_map_rgb(cr, cg, cb, 1.18f + dawn_dusk_factor() * 0.22f);
                        const float alpha = clamp01((1.0f - transmittance) * alpha_scale);
                        glColor4f(cr, cg, cb, alpha);
                    }
                    glVertex2f(px, layer_y + py * 0.34f + row * 0.004f);
                }
            }
            glEnd();
        }
    }
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);

    glEnable(GL_FOG);
    glEnable(GL_DEPTH_TEST);
    glPopMatrix();
    glMatrixMode(GL_PROJECTION);
    glPopMatrix();
    glMatrixMode(GL_MODELVIEW);
}

static void draw_skybox() {
    float top[3];
    float horizon[3];
    float light = 1.0f;
    sky_colors(top, horizon, &light);

    glMatrixMode(GL_MODELVIEW);
    glPushMatrix();
    glLoadIdentity();
    glRotatef(g_camera_pitch * 57.2957795f, 1, 0, 0);
    glRotatef(g_camera_yaw * 57.2957795f, 0, 1, 0);

    glDisable(GL_DEPTH_TEST);
    glDisable(GL_TEXTURE_2D);
    glDisable(GL_CULL_FACE);
    glDisable(GL_FOG);
    glDepthMask(GL_FALSE);

    const float radius = 135.0f;
    const int rings = 8;
    const int segments = 64;
    for (int ring = 0; ring < rings; ring++) {
        const float v0 = static_cast<float>(ring) / static_cast<float>(rings);
        const float v1 = static_cast<float>(ring + 1) / static_cast<float>(rings);
        const float e0 = 0.04f + v0 * 1.18f;
        const float e1 = 0.04f + v1 * 1.18f;
        const float y0 = std::sin(e0) * radius - 12.0f;
        const float y1 = std::sin(e1) * radius - 12.0f;
        const float r0 = std::cos(e0) * radius;
        const float r1 = std::cos(e1) * radius;
        const float c0 = smoothstep(0.0f, 1.0f, v0);
        const float c1 = smoothstep(0.0f, 1.0f, v1);

        glBegin(GL_QUAD_STRIP);
        for (int seg = 0; seg <= segments; seg++) {
            const float a = static_cast<float>(seg) / static_cast<float>(segments) * 6.2831853f;
            const float ca = std::cos(a);
            const float sa = std::sin(a);
            glColor3f(
                horizon[0] * (1.0f - c0) + top[0] * c0,
                horizon[1] * (1.0f - c0) + top[1] * c0,
                horizon[2] * (1.0f - c0) + top[2] * c0);
            glVertex3f(ca * r0, y0, sa * r0);
            glColor3f(
                horizon[0] * (1.0f - c1) + top[0] * c1,
                horizon[1] * (1.0f - c1) + top[1] * c1,
                horizon[2] * (1.0f - c1) + top[2] * c1);
            glVertex3f(ca * r1, y1, sa * r1);
        }
        glEnd();
    }

    glDepthMask(GL_TRUE);
    glEnable(GL_FOG);
    glEnable(GL_CULL_FACE);
    glEnable(GL_DEPTH_TEST);
    glPopMatrix();
}

static void draw_water() {
    const double t = now_seconds() - g_start_time;
    float top[3];
    float horizon[3];
    float light = 1.0f;
    sky_colors(top, horizon, &light);
    float sun[3];
    sun_direction(sun);
    const float day_spec = smoothstep(-0.05f, 0.35f, sun_height()) * (1.0f - g_rain * 0.70f);

    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glDisable(GL_CULL_FACE);

    const int half = 68;
    const float step = 1.55f;
    for (int z = -half; z < half; z++) {
        glBegin(GL_TRIANGLE_STRIP);
        for (int x = -half; x <= half; x++) {
            for (int row = 0; row < 2; row++) {
                const float wx = g_boat_x + x * step;
                const float wz = g_boat_z + (z + row) * step;
                const float y = wave_height(wx, wz, static_cast<float>(t));
                float n[3];
                wave_normal(wx, wz, static_cast<float>(t), n);
                float view[3] = {g_boat_x - wx, 5.0f - y, g_boat_z + g_camera_distance - wz};
                normalize3(view);
                const float diffuse = std::max(0.0f, n[0] * sun[0] + n[1] * sun[1] + n[2] * sun[2]);
                const float n_dot_v = clamp01(n[0] * view[0] + n[1] * view[1] + n[2] * view[2]);
                const float fresnel = fresnel_schlick(n_dot_v);
                const float roughness = slope_roughness(n);
                const float ao = slope_ambient_occlusion(n);
                float halfway[3] = {
                    view[0] + sun[0],
                    view[1] + sun[1],
                    view[2] + sun[2],
                };
                normalize3(halfway);
                const float n_dot_h = std::max(0.0f, n[0] * halfway[0] + n[1] * halfway[1] + n[2] * halfway[2]);
                const float spec = ggx_distribution(n_dot_h, roughness) * day_spec * (0.08f + fresnel * 0.55f);
                const float dist = std::sqrt((wx - g_boat_x) * (wx - g_boat_x) + (wz - g_boat_z) * (wz - g_boat_z));
                const float fog = exp_fog_factor(dist * 0.045f, 0.50f + g_rain * 0.22f);
                const float radial = std::sqrt(static_cast<float>(x * x + (z + row) * (z + row))) / static_cast<float>(half);
                const float fade = 1.0f - smoothstep(0.74f, 1.0f, radial);
                const float depth_tint = clamp01(0.58f + y * 0.45f);
                const float shallow = std::max(0.0f, n[1] - 0.82f) * (1.0f - g_rain * 0.35f);
                const float sky_reflect = fresnel * (0.42f + n[1] * 0.18f);
                const float body_r = 0.004f + 0.012f * shallow;
                const float body_g = 0.075f + 0.055f * shallow + 0.025f * diffuse;
                const float body_b = 0.145f + 0.120f * shallow + 0.050f * diffuse;
                const float deep_r = body_r * (0.68f + depth_tint * 0.20f);
                const float deep_g = body_g * (0.74f + depth_tint * 0.22f);
                const float deep_b = body_b * (0.88f + depth_tint * 0.18f);
                float r = deep_r + horizon[0] * sky_reflect * 0.45f + spec * 0.68f;
                float g = deep_g + horizon[1] * sky_reflect * 0.42f + spec * 0.76f;
                float b = deep_b + horizon[2] * sky_reflect * 0.38f + spec * 0.92f;
                r *= 0.70f + ao * 0.30f;
                g *= 0.72f + ao * 0.28f;
                b *= 0.76f + ao * 0.24f;
                r = r * (1.0f - g_rain * 0.24f) + 0.030f * g_rain;
                g = g * (1.0f - g_rain * 0.20f) + 0.070f * g_rain;
                b = b * (1.0f - g_rain * 0.16f) + 0.105f * g_rain;
                const float lantern = night_factor() * (1.0f - g_rain * 0.35f);
                if (lantern > 0.02f) {
                    const float lx = wx - g_boat_x;
                    const float lz = wz - g_boat_z;
                    const float ldist2 = lx * lx + lz * lz + 2.0f;
                    const float point = lantern * std::min(1.0f, 16.0f / ldist2) * std::max(0.0f, n[1]);
                    r += point * 1.45f;
                    g += point * 0.84f;
                    b += point * 0.36f;
                }
                r = horizon[0] * (1.0f - fog) + r * fog;
                g = horizon[1] * (1.0f - fog) + g * fog;
                b = horizon[2] * (1.0f - fog) + b * fog;
                tone_map_rgb(r, g, b, 1.25f + dawn_dusk_factor() * 0.35f);
                glColor4f(r * light, g * light, b * light, 0.92f * fade);
                glVertex3f(wx, y, wz);
            }
        }
        glEnd();
    }

    glColor4f(0.56f, 0.78f, 0.88f, (0.18f + g_boat_speed * 0.018f) * (1.0f - g_rain * 0.62f));
    glLineWidth(1.0f);
    glBegin(GL_LINES);
    for (int i = -30; i <= 30; i += 2) {
        const float wx = g_boat_x + i * 2.2f;
        const float wz = g_boat_z - 12.0f + std::sin(i * 0.7f + static_cast<float>(t)) * 7.0f;
        const float y = wave_height(wx, wz, static_cast<float>(t)) + 0.025f;
        glVertex3f(wx - 0.7f, y, wz);
        glVertex3f(wx + 1.1f, y + 0.01f, wz + 0.25f);
    }
    glEnd();
    glEnable(GL_CULL_FACE);
}

static void draw_marker_geometry(const OceanMarker &marker) {
    const float t = static_cast<float>(now_seconds() - g_start_time);
    const float y = wave_height(marker.x, marker.z, t);
    glPushMatrix();
    glTranslatef(marker.x, y, marker.z);
    glScalef(marker.size, marker.size, marker.size);
    glDisable(GL_TEXTURE_2D);

    if (marker.type == 3) {
        glTranslatef(0.0f, -0.18f, 0.0f);
        for (int tier = 0; tier < 4; tier++) {
            const float h0 = tier * 0.38f;
            const float h1 = h0 + 0.42f + tier * 0.12f;
            const float r0 = 2.35f - tier * 0.38f;
            const float r1 = 1.62f - tier * 0.28f;
            glColor3f(0.28f + tier * 0.035f, 0.23f + tier * 0.025f, 0.20f + tier * 0.018f);
            glBegin(GL_QUAD_STRIP);
            for (int i = 0; i <= 18; i++) {
                const float a = static_cast<float>(i) / 18.0f * 6.2831853f;
                const float wobble = 1.0f + std::sin(a * 3.0f + marker.x * 0.01f) * 0.13f;
                glVertex3f(std::cos(a) * r0 * wobble, h0, std::sin(a) * r0 * (1.0f - tier * 0.04f));
                glVertex3f(std::cos(a) * r1 * wobble, h1, std::sin(a) * r1 * (1.0f - tier * 0.04f));
            }
            glEnd();
        }
        glColor3f(0.16f, 0.38f, 0.17f);
        glBegin(GL_TRIANGLE_FAN);
        glVertex3f(0.0f, 1.85f, 0.0f);
        for (int i = 0; i <= 24; i++) {
            const float a = static_cast<float>(i) / 24.0f * 6.2831853f;
            glVertex3f(std::cos(a) * 1.35f, 1.52f + std::sin(i * 0.9f) * 0.05f, std::sin(a) * 1.15f);
        }
        glEnd();
        for (int tree = 0; tree < 5; tree++) {
            const float a = tree * 1.37f + marker.z * 0.01f;
            const float tx = std::cos(a) * (0.45f + 0.18f * tree);
            const float tz = std::sin(a) * (0.38f + 0.16f * tree);
            glColor3f(0.18f, 0.10f, 0.055f);
            glBegin(GL_QUADS);
            glVertex3f(tx - 0.035f, 1.45f, tz);
            glVertex3f(tx + 0.035f, 1.45f, tz);
            glVertex3f(tx + 0.035f, 1.92f, tz);
            glVertex3f(tx - 0.035f, 1.92f, tz);
            glEnd();
            glColor3f(0.20f, 0.46f + tree * 0.018f, 0.20f);
            glBegin(GL_TRIANGLE_FAN);
            glVertex3f(tx, 2.10f, tz);
            for (int i = 0; i <= 14; i++) {
                const float ca = static_cast<float>(i) / 14.0f * 6.2831853f;
                glVertex3f(tx + std::cos(ca) * 0.18f, 1.88f + std::sin(ca) * 0.12f, tz + std::sin(ca) * 0.18f);
            }
            glEnd();
        }
        glPopMatrix();
        return;
    }

    if (marker.type == 4) {
        glRotatef(-18.0f + std::fmod(marker.x + marker.z, 36.0f), 0, 1, 0);
        glColor3f(0.10f, 0.065f, 0.045f);
        glBegin(GL_QUADS);
        glVertex3f(-1.70f, 0.05f, -0.55f);
        glVertex3f(1.70f, 0.05f, -0.55f);
        glVertex3f(1.15f, -0.35f, 0.72f);
        glVertex3f(-1.15f, -0.35f, 0.72f);
        glVertex3f(-1.70f, 0.05f, -0.55f);
        glVertex3f(-1.15f, -0.35f, 0.72f);
        glVertex3f(-0.58f, -0.52f, 0.45f);
        glVertex3f(-1.05f, -0.12f, -0.46f);
        glVertex3f(1.70f, 0.05f, -0.55f);
        glVertex3f(1.05f, -0.12f, -0.46f);
        glVertex3f(0.58f, -0.52f, 0.45f);
        glVertex3f(1.15f, -0.35f, 0.72f);
        glEnd();
        glColor3f(0.58f, 0.45f, 0.29f);
        for (int mast = -1; mast <= 1; mast++) {
            const float mx = mast * 0.72f;
            glBegin(GL_QUADS);
            glVertex3f(mx - 0.025f, -0.05f, -0.05f);
            glVertex3f(mx + 0.025f, -0.05f, -0.05f);
            glVertex3f(mx + 0.025f, 1.95f + (mast == 0 ? 0.35f : 0.0f), -0.05f);
            glVertex3f(mx - 0.025f, 1.95f + (mast == 0 ? 0.35f : 0.0f), -0.05f);
            glEnd();
            glColor3f(0.77f, 0.72f, 0.62f);
            glBegin(GL_TRIANGLES);
            glVertex3f(mx + 0.05f, 1.65f, -0.04f);
            glVertex3f(mx + 0.05f, 0.45f, -0.04f);
            glVertex3f(mx + 0.58f, 0.68f, -0.04f);
            glEnd();
            glColor3f(0.58f, 0.45f, 0.29f);
        }
        glColor3f(0.03f, 0.025f, 0.02f);
        glBegin(GL_LINES);
        for (int mast = -1; mast <= 1; mast++) {
            const float mx = mast * 0.72f;
            glVertex3f(mx, 1.9f, -0.05f);
            glVertex3f(-1.55f, 0.10f, -0.55f);
            glVertex3f(mx, 1.9f, -0.05f);
            glVertex3f(1.55f, 0.10f, -0.55f);
        }
        glEnd();
        glPopMatrix();
        return;
    }

    if (marker.type == 2) {
        glColor3f(0.18f, 0.17f, 0.15f);
        glBegin(GL_TRIANGLES);
        glVertex3f(-1.1f, 0.0f, -0.8f);
        glVertex3f(1.0f, 0.0f, -0.7f);
        glVertex3f(0.1f, 0.9f, -0.2f);
        glVertex3f(1.0f, 0.0f, -0.7f);
        glVertex3f(0.8f, 0.0f, 1.0f);
        glVertex3f(0.1f, 0.9f, -0.2f);
        glVertex3f(0.8f, 0.0f, 1.0f);
        glVertex3f(-0.9f, 0.0f, 0.8f);
        glVertex3f(0.1f, 0.9f, -0.2f);
        glVertex3f(-0.9f, 0.0f, 0.8f);
        glVertex3f(-1.1f, 0.0f, -0.8f);
        glVertex3f(0.1f, 0.9f, -0.2f);
        glEnd();
        glPopMatrix();
        return;
    }

    glColor3f(marker.type == 1 ? 0.95f : 0.82f, marker.type == 1 ? 0.78f : 0.08f, marker.type == 1 ? 0.18f : 0.06f);
    glBegin(GL_QUADS);
    glVertex3f(-0.35f, 0.05f, -0.35f);
    glVertex3f(0.35f, 0.05f, -0.35f);
    glVertex3f(0.35f, 0.65f, -0.35f);
    glVertex3f(-0.35f, 0.65f, -0.35f);
    glVertex3f(0.35f, 0.05f, 0.35f);
    glVertex3f(-0.35f, 0.05f, 0.35f);
    glVertex3f(-0.35f, 0.65f, 0.35f);
    glVertex3f(0.35f, 0.65f, 0.35f);
    glVertex3f(-0.35f, 0.05f, 0.35f);
    glVertex3f(-0.35f, 0.05f, -0.35f);
    glVertex3f(-0.35f, 0.65f, -0.35f);
    glVertex3f(-0.35f, 0.65f, 0.35f);
    glVertex3f(0.35f, 0.05f, -0.35f);
    glVertex3f(0.35f, 0.05f, 0.35f);
    glVertex3f(0.35f, 0.65f, 0.35f);
    glVertex3f(0.35f, 0.65f, -0.35f);
    glEnd();

    glColor3f(0.88f, 0.88f, 0.78f);
    glBegin(GL_QUADS);
    glVertex3f(-0.07f, 0.65f, -0.07f);
    glVertex3f(0.07f, 0.65f, -0.07f);
    glVertex3f(0.07f, 1.45f, -0.07f);
    glVertex3f(-0.07f, 1.45f, -0.07f);
    glVertex3f(0.07f, 0.65f, 0.07f);
    glVertex3f(-0.07f, 0.65f, 0.07f);
    glVertex3f(-0.07f, 1.45f, 0.07f);
    glVertex3f(0.07f, 1.45f, 0.07f);
    glEnd();

    if (marker.type == 1) {
        const float glow = 0.35f + night_factor() * 0.65f;
        glEnable(GL_BLEND);
        glBlendFunc(GL_SRC_ALPHA, GL_ONE);
        glColor4f(1.0f, 0.70f, 0.20f, 0.45f * glow);
        glBegin(GL_TRIANGLE_FAN);
        glVertex3f(0.0f, 1.55f, 0.0f);
        for (int i = 0; i <= 24; i++) {
            const float a = static_cast<float>(i) / 24.0f * 6.2831853f;
            glVertex3f(std::cos(a) * 0.55f, 1.55f + std::sin(a) * 0.55f, 0.0f);
        }
        glEnd();
        glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    }

    glPopMatrix();
}

static void draw_world_markers() {
    glDisable(GL_CULL_FACE);
    for (const auto &chunk : g_marker_chunks) {
        for (const OceanMarker &marker : chunk.second) {
            const float dx = marker.x - g_boat_x;
            const float dz = marker.z - g_boat_z;
            if (dx * dx + dz * dz <= 430.0f * 430.0f) {
                draw_marker_geometry(marker);
            }
        }
    }
    glEnable(GL_CULL_FACE);
}

static void draw_boat() {
    const double t = now_seconds() - g_start_time;
    const float bob = wave_height(g_boat_x, g_boat_z, static_cast<float>(t)) + 0.20f;
    glPushMatrix();
    glTranslatef(g_boat_x, bob, g_boat_z);
    glRotatef(g_boat_yaw * 57.2957795f, 0, 1, 0);
    glRotatef(std::sin(static_cast<float>(t) * 2.2f + g_boat_x) * 2.2f, 0, 0, 1);

    glDisable(GL_TEXTURE_2D);
    glColor3f(0.30f, 0.13f, 0.055f);
    glBegin(GL_QUADS);
    glVertex3f(-1.25f, 0.10f, -1.80f);
    glVertex3f(1.25f, 0.10f, -1.80f);
    glVertex3f(0.82f, 0.02f, 1.85f);
    glVertex3f(-0.82f, 0.02f, 1.85f);
    glVertex3f(-0.82f, 0.02f, 1.85f);
    glVertex3f(0.82f, 0.02f, 1.85f);
    glVertex3f(0.44f, -0.45f, 1.35f);
    glVertex3f(-0.44f, -0.45f, 1.35f);
    glVertex3f(-1.25f, 0.10f, -1.80f);
    glVertex3f(-0.82f, 0.02f, 1.85f);
    glVertex3f(-0.44f, -0.45f, 1.35f);
    glVertex3f(-0.72f, -0.36f, -1.40f);
    glVertex3f(1.25f, 0.10f, -1.80f);
    glVertex3f(0.72f, -0.36f, -1.40f);
    glVertex3f(0.44f, -0.45f, 1.35f);
    glVertex3f(0.82f, 0.02f, 1.85f);
    glEnd();

    glColor3f(0.72f, 0.58f, 0.38f);
    glBegin(GL_QUADS);
    glVertex3f(-0.08f, 0.00f, -0.45f);
    glVertex3f(0.08f, 0.00f, -0.45f);
    glVertex3f(0.08f, 1.85f, -0.45f);
    glVertex3f(-0.08f, 1.85f, -0.45f);
    glEnd();

    glColor3f(0.88f, 0.86f, 0.76f);
    glBegin(GL_TRIANGLES);
    glVertex3f(0.10f, 1.70f, -0.45f);
    glVertex3f(0.10f, 0.28f, -0.45f);
    glVertex3f(1.25f, 0.34f, -0.45f);
    glEnd();

    const float lantern = night_factor() * (1.0f - g_rain * 0.25f);
    if (lantern > 0.02f) {
        glEnable(GL_BLEND);
        glBlendFunc(GL_SRC_ALPHA, GL_ONE);
        glDisable(GL_CULL_FACE);
        glColor4f(1.0f, 0.62f, 0.24f, 0.95f * lantern);
        glBegin(GL_QUADS);
        glVertex3f(-0.18f, 0.75f, 0.24f);
        glVertex3f(0.18f, 0.75f, 0.24f);
        glVertex3f(0.18f, 1.12f, 0.24f);
        glVertex3f(-0.18f, 1.12f, 0.24f);
        glEnd();
        glColor4f(1.0f, 0.54f, 0.18f, 0.20f * lantern);
        glBegin(GL_TRIANGLE_FAN);
        glVertex3f(0.0f, 0.94f, 0.26f);
        for (int i = 0; i <= 32; i++) {
            const float a = static_cast<float>(i) / 32.0f * 6.2831853f;
            glVertex3f(std::cos(a) * 1.1f, 0.94f + std::sin(a) * 0.65f, 0.28f);
        }
        glEnd();
        glEnable(GL_CULL_FACE);
        glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    }

    glPopMatrix();
}

static void draw_rain() {
    if (g_rain <= 0.02f) {
        return;
    }
    const float t = static_cast<float>(now_seconds() - g_start_time);
    glDisable(GL_TEXTURE_2D);
    glDisable(GL_CULL_FACE);
    glColor4f(0.66f, 0.78f, 0.86f, 0.42f * g_rain);
    glLineWidth(1.0f);
    glBegin(GL_LINES);
    for (int i = 0; i < 150; i++) {
        const float seed = static_cast<float>(i) * 12.9898f;
        const float rx = g_boat_x + std::fmod(std::sin(seed) * 43758.5f, 64.0f) - 32.0f;
        const float rz = g_boat_z + std::fmod(std::cos(seed * 1.31f) * 21758.5f, 64.0f) - 32.0f;
        const float fall = std::fmod(t * 19.0f + i * 0.37f, 9.0f);
        glVertex3f(rx, 8.0f - fall, rz);
        glVertex3f(rx + 0.26f, 6.8f - fall, rz - 0.36f);
    }
    glEnd();
    glEnable(GL_CULL_FACE);
}

static void draw_screen_disc(float x, float y, float radius_x, float radius_y, float r, float g, float b, float alpha) {
    if (alpha <= 0.001f) {
        return;
    }
    glBegin(GL_TRIANGLE_FAN);
    glColor4f(r, g, b, alpha);
    glVertex2f(x, y);
    glColor4f(r, g, b, 0.0f);
    for (int i = 0; i <= 48; i++) {
        const float a = static_cast<float>(i) / 48.0f * 6.2831853f;
        glVertex2f(x + std::cos(a) * radius_x, y + std::sin(a) * radius_y);
    }
    glEnd();
}

static void draw_bloom_overlay() {
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
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE);

    const float sun_angle = (g_day_time - 0.25f) * 6.2831853f;
    const float sx = std::cos(sun_angle) * 0.68f;
    const float sy = std::sin(sun_angle) * 0.74f;
    const float body_y = sy < -0.05f ? -sy : sy;
    const float daylight = smoothstep(-0.05f, 0.28f, sun_height()) * (1.0f - g_rain * 0.55f);
    draw_screen_disc(sx, body_y, 0.24f, 0.24f, 1.0f, 0.68f, 0.28f, 0.17f * daylight);
    draw_screen_disc(sx, body_y, 0.48f, 0.34f, 1.0f, 0.58f, 0.22f, 0.07f * daylight);

    const float golden = dawn_dusk_factor() * (1.0f - g_rain * 0.45f);
    draw_screen_disc(0.0f, -0.42f, 1.25f, 0.28f, 1.0f, 0.48f, 0.18f, 0.11f * golden);

    const float lantern = night_factor() * (1.0f - g_rain * 0.20f);
    draw_screen_disc(0.0f, -0.18f, 0.28f, 0.18f, 1.0f, 0.48f, 0.12f, 0.12f * lantern);

    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glEnable(GL_CULL_FACE);
    glEnable(GL_FOG);
    glEnable(GL_DEPTH_TEST);

    glPopMatrix();
    glMatrixMode(GL_PROJECTION);
    glPopMatrix();
    glMatrixMode(GL_MODELVIEW);
}

Bool php_ocean_init(String title, Int width, Int height) {
    SetProcessDPIAware();
    SetConsoleOutputCP(65001);
    g_width = static_cast<int>(width);
    g_height = static_cast<int>(height);
    g_start_time = now_seconds();

    WNDCLASS wc{};
    wc.style = CS_OWNDC | CS_HREDRAW | CS_VREDRAW;
    wc.lpfnWndProc = wnd_proc;
    wc.hInstance = GetModuleHandle(nullptr);
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.lpszClassName = "TypePhpOceanWindow";
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
    glEnable(GL_BLEND);
    glBlendFunc(GL_SRC_ALPHA, GL_ONE_MINUS_SRC_ALPHA);
    glEnable(GL_CULL_FACE);
    glClearColor(0.42f, 0.67f, 0.88f, 1.0f);
    enable_mouse_capture();
    return true;
}

void php_ocean_shutdown() {
    disable_mouse_capture();
    g_marker_chunks.clear();
    g_pending_markers.clear();
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

Bool php_ocean_should_close() {
    return g_should_close;
}

void php_ocean_poll_events() {
    MSG msg;
    while (PeekMessage(&msg, nullptr, 0, 0, PM_REMOVE)) {
        TranslateMessage(&msg);
        DispatchMessage(&msg);
    }
}

Bool php_ocean_key_pressed(Int key) {
    const int k = static_cast<int>(key);
    return k >= 0 && k < 256 && g_keys[k];
}

Float php_ocean_mouse_delta_x() {
    const double value = g_mouse_dx;
    g_mouse_dx = 0.0;
    return value;
}

Float php_ocean_mouse_delta_y() {
    const double value = g_mouse_dy;
    g_mouse_dy = 0.0;
    return value;
}

Float php_ocean_get_time() {
    return now_seconds();
}

void php_ocean_sleep(Int milliseconds) {
    Sleep(static_cast<DWORD>(std::max(0, static_cast<int>(milliseconds))));
}

Bool php_ocean_confirm_exit() {
    disable_mouse_capture();
    const int result = MessageBoxW(
        g_hwnd,
        L"\x662f\x5426\x9000\x51fa\x6d77\x6d0b\x573a\x666f\xff1f",
        L"\x786e\x8ba4\x9000\x51fa",
        MB_ICONQUESTION | MB_YESNO | MB_DEFBUTTON2);
    enable_mouse_capture();
    g_mouse_dx = 0.0;
    g_mouse_dy = 0.0;
    return result == IDYES;
}

void php_ocean_set_boat(Float x, Float z, Float yaw, Float speed) {
    g_boat_x = static_cast<float>(x);
    g_boat_z = static_cast<float>(z);
    g_boat_yaw = static_cast<float>(yaw);
    g_boat_speed = static_cast<float>(speed);
}

void php_ocean_set_camera(Float yaw, Float pitch, Float distance) {
    g_camera_yaw = static_cast<float>(yaw);
    g_camera_pitch = static_cast<float>(pitch);
    g_camera_distance = std::max(8.0f, std::min(42.0f, static_cast<float>(distance)));
}

void php_ocean_set_environment(Float day_time, Int weather, Float weather_mix, Float rain_amount) {
    g_day_time = clamp01(static_cast<float>(day_time));
    g_weather = std::max(0, std::min(2, static_cast<int>(weather)));
    g_weather_mix = clamp01(static_cast<float>(weather_mix));
    g_rain = clamp01(static_cast<float>(rain_amount));
}

void php_ocean_begin_chunk(Int chunk_x, Int chunk_z) {
    g_pending_chunk = ChunkKey{static_cast<int>(chunk_x), static_cast<int>(chunk_z)};
    g_pending_markers.clear();
    g_has_pending_chunk = true;
}

void php_ocean_add_marker(Float x, Float z, Int type, Float size) {
    if (!g_has_pending_chunk) {
        return;
    }
    OceanMarker marker{};
    marker.x = static_cast<float>(x);
    marker.z = static_cast<float>(z);
    marker.type = std::max(0, std::min(2, static_cast<int>(type)));
    marker.size = std::max(0.35f, std::min(4.0f, static_cast<float>(size)));
    g_pending_markers.push_back(marker);
}

void php_ocean_commit_chunk(Int chunk_x, Int chunk_z) {
    const ChunkKey key{static_cast<int>(chunk_x), static_cast<int>(chunk_z)};
    if (!g_has_pending_chunk) {
        return;
    }
    g_marker_chunks[key] = g_pending_markers;
    g_pending_markers.clear();
    g_pending_chunk = key;
    g_has_pending_chunk = false;
}

void php_ocean_remove_chunk(Int chunk_x, Int chunk_z) {
    const ChunkKey key{static_cast<int>(chunk_x), static_cast<int>(chunk_z)};
    g_marker_chunks.erase(key);
}

void php_ocean_render_frame() {
    SetWindowTextA(g_hwnd, "TypePHP Ocean Demo - OpenGL");
    glViewport(0, 0, g_width, std::max(1, g_height));
    glClear(GL_COLOR_BUFFER_BIT | GL_DEPTH_BUFFER_BIT);
    draw_sky();
    glClear(GL_DEPTH_BUFFER_BIT);

    glMatrixMode(GL_PROJECTION);
    glLoadIdentity();
    set_perspective(62.0, static_cast<double>(g_width) / std::max(1, g_height), 0.1, 260.0);

    draw_skybox();

    glMatrixMode(GL_MODELVIEW);
    glLoadIdentity();
    glRotatef(g_camera_pitch * 57.2957795f, 1, 0, 0);
    glRotatef(g_camera_yaw * 57.2957795f, 0, 1, 0);
    glTranslatef(-g_boat_x, -4.2f, -g_boat_z - g_camera_distance);

    const GLfloat fog_color[] = {0.38f + g_rain * 0.04f, 0.52f + g_rain * 0.02f, 0.61f + g_rain * 0.03f, 1.0f};
    glEnable(GL_FOG);
    glFogfv(GL_FOG_COLOR, fog_color);
    glFogi(GL_FOG_MODE, GL_EXP2);
    glFogf(GL_FOG_DENSITY, 0.010f + g_rain * 0.006f + active_weather_strength() * 0.003f);

    draw_water();
    draw_world_markers();
    draw_boat();
    draw_rain();

    glDisable(GL_FOG);
    draw_bloom_overlay();
    SwapBuffers(g_hdc);
}
