#include <jni.h>
#include <phpx.h>
#include <typephp_runtime.h>

#include <mutex>

TYPEPHP_RUNTIME_INIT_FUNCTION(typephp_android_hello);
extern void php_typephp_application_did_launch();
extern void php_typephp_application_control_activated(php::Int control_id);

static JavaVM *java_vm = nullptr;
static jobject activity = nullptr;
static jclass activity_class = nullptr;
static std::once_flag runtime_once;
static int runtime_status = -1;

static JNIEnv *get_env()
{
    JNIEnv *env = nullptr;
    if (java_vm == nullptr || java_vm->GetEnv(reinterpret_cast<void **>(&env), JNI_VERSION_1_6) != JNI_OK) {
        return nullptr;
    }
    return env;
}

static jmethodID method(JNIEnv *env, const char *name, const char *signature)
{
    return activity_class == nullptr ? nullptr : env->GetMethodID(activity_class, name, signature);
}

static jstring to_java_string(JNIEnv *env, const php::Str &value)
{
    return env->NewStringUTF(value.data());
}

static php::Str from_java_string(JNIEnv *env, jstring value)
{
    if (value == nullptr) {
        return php::Str();
    }
    const char *utf8 = env->GetStringUTFChars(value, nullptr);
    php::Str result(utf8 == nullptr ? "" : utf8);
    if (utf8 != nullptr) {
        env->ReleaseStringUTFChars(value, utf8);
    }
    return result;
}

static int ensure_runtime()
{
    std::call_once(runtime_once, []() {
        char application_name[] = "typephp_android_hello";
        char *argv[] = {application_name, nullptr};
        runtime_status = TYPEPHP_RUNTIME_INIT(typephp_android_hello)(1, argv);
    });
    return runtime_status;
}

extern "C" JNIEXPORT jint JNICALL JNI_OnLoad(JavaVM *vm, void *)
{
    java_vm = vm;
    return JNI_VERSION_1_6;
}

extern "C" JNIEXPORT void JNICALL
Java_com_swoole_typephp_MainActivity_nativeApplicationDidLaunch(JNIEnv *env, jobject instance)
{
    if (activity != nullptr) {
        env->DeleteGlobalRef(activity);
    }
    if (activity_class != nullptr) {
        env->DeleteGlobalRef(activity_class);
    }
    activity = env->NewGlobalRef(instance);
    jclass local_class = env->GetObjectClass(instance);
    activity_class = reinterpret_cast<jclass>(env->NewGlobalRef(local_class));
    env->DeleteLocalRef(local_class);

    if (ensure_runtime() == 0) {
        php_typephp_application_did_launch();
    }
}

extern "C" JNIEXPORT void JNICALL
Java_com_swoole_typephp_MainActivity_nativeControlActivated(JNIEnv *, jobject, jint control_id)
{
    if (runtime_status == 0) {
        php_typephp_application_control_activated(static_cast<php::Int>(control_id));
    }
}

void php_ui_create_window(php::Str title, php::Int width, php::Int height)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiCreateWindow", "(Ljava/lang/String;II)V");
    if (target == nullptr) {
        return;
    }
    jstring java_title = to_java_string(env, title);
    env->CallVoidMethod(activity, target, java_title, static_cast<jint>(width), static_cast<jint>(height));
    env->DeleteLocalRef(java_title);
}

php::Int php_ui_add_image(php::Str resource_name, php::Int x, php::Int y, php::Int width, php::Int height)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiAddImage", "(Ljava/lang/String;IIII)I");
    if (target == nullptr) {
        return 0;
    }
    jstring java_name = to_java_string(env, resource_name);
    jint result = env->CallIntMethod(activity, target, java_name, static_cast<jint>(x), static_cast<jint>(y),
                                     static_cast<jint>(width), static_cast<jint>(height));
    env->DeleteLocalRef(java_name);
    return static_cast<php::Int>(result);
}

php::Int php_ui_add_label(php::Str text, php::Int x, php::Int y, php::Int width, php::Int height,
                          php::Int font_size, php::Bool bold)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiAddLabel", "(Ljava/lang/String;IIIIIZ)I");
    if (target == nullptr) {
        return 0;
    }
    jstring java_text = to_java_string(env, text);
    jint result = env->CallIntMethod(activity, target, java_text, static_cast<jint>(x), static_cast<jint>(y),
                                     static_cast<jint>(width), static_cast<jint>(height),
                                     static_cast<jint>(font_size), static_cast<jboolean>(bold));
    env->DeleteLocalRef(java_text);
    return static_cast<php::Int>(result);
}

php::Int php_ui_add_text_input(php::Str hint, php::Int x, php::Int y, php::Int width, php::Int height)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiAddTextInput", "(Ljava/lang/String;IIII)I");
    if (target == nullptr) {
        return 0;
    }
    jstring java_hint = to_java_string(env, hint);
    jint result = env->CallIntMethod(activity, target, java_hint, static_cast<jint>(x), static_cast<jint>(y),
                                     static_cast<jint>(width), static_cast<jint>(height));
    env->DeleteLocalRef(java_hint);
    return static_cast<php::Int>(result);
}

php::Int php_ui_add_button(php::Str title, php::Int x, php::Int y, php::Int width, php::Int height, php::Int style)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiAddButton", "(Ljava/lang/String;IIIII)I");
    if (target == nullptr) {
        return 0;
    }
    jstring java_title = to_java_string(env, title);
    jint result = env->CallIntMethod(activity, target, java_title, static_cast<jint>(x), static_cast<jint>(y),
                                     static_cast<jint>(width), static_cast<jint>(height), static_cast<jint>(style));
    env->DeleteLocalRef(java_title);
    return static_cast<php::Int>(result);
}

void php_ui_set_control_text(php::Int control_id, php::Str text)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiSetControlText", "(ILjava/lang/String;)V");
    if (target == nullptr) {
        return;
    }
    jstring java_text = to_java_string(env, text);
    env->CallVoidMethod(activity, target, static_cast<jint>(control_id), java_text);
    env->DeleteLocalRef(java_text);
}

php::Str php_ui_get_control_text(php::Int control_id)
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiGetControlText", "(I)Ljava/lang/String;");
    if (target == nullptr) {
        return php::Str();
    }
    auto value = static_cast<jstring>(env->CallObjectMethod(activity, target, static_cast<jint>(control_id)));
    php::Str result = from_java_string(env, value);
    if (value != nullptr) {
        env->DeleteLocalRef(value);
    }
    return result;
}

void php_ui_show_window()
{
    JNIEnv *env = get_env();
    jmethodID target = env == nullptr ? nullptr : method(env, "uiShowWindow", "()V");
    if (target != nullptr) {
        env->CallVoidMethod(activity, target);
    }
}
