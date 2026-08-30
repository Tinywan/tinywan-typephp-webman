#include <phpx.h>
#include <jni.h>
#include <string>
#include <cstring>
#include <unordered_map>
#include <vector>

using namespace php;

static JavaVM *jvm = nullptr;
static JNIEnv *env = nullptr;

//----------------------------------------------------------------------
// JNI helper: check and rethrow JNI exceptions as PHP errors
//----------------------------------------------------------------------
static void check_jni_exception() {
    if (env->ExceptionCheck()) {
        env->ExceptionDescribe();
        env->ExceptionClear();
        throwError("JNI exception occurred");
    }
}

//----------------------------------------------------------------------
// Cached reflection helpers (java.lang.reflect.* method IDs)
//----------------------------------------------------------------------
static jmethodID s_Class_getName = nullptr;
static jmethodID s_Class_getDeclaredConstructors = nullptr;
static jmethodID s_Class_getMethods = nullptr;
static jmethodID s_Class_getDeclaredFields = nullptr;
static jmethodID s_Constructor_getParameterTypes = nullptr;
static jmethodID s_Method_getName = nullptr;
static jmethodID s_Method_getParameterTypes = nullptr;
static jmethodID s_Method_getReturnType = nullptr;
static jmethodID s_Field_getName = nullptr;
static jmethodID s_Field_getType = nullptr;
static jmethodID s_Field_getModifiers = nullptr;
static bool s_reflection_inited = false;

static void init_reflection() {
    if (s_reflection_inited) return;

    jclass classCls = env->FindClass("java/lang/Class");
    s_Class_getName = env->GetMethodID(classCls, "getName", "()Ljava/lang/String;");
    s_Class_getDeclaredConstructors = env->GetMethodID(classCls, "getDeclaredConstructors", "()[Ljava/lang/reflect/Constructor;");
    s_Class_getMethods = env->GetMethodID(classCls, "getMethods", "()[Ljava/lang/reflect/Method;");
    s_Class_getDeclaredFields = env->GetMethodID(classCls, "getDeclaredFields", "()[Ljava/lang/reflect/Field;");
    env->DeleteLocalRef(classCls);

    jclass ctorCls = env->FindClass("java/lang/reflect/Constructor");
    s_Constructor_getParameterTypes = env->GetMethodID(ctorCls, "getParameterTypes", "()[Ljava/lang/Class;");
    env->DeleteLocalRef(ctorCls);

    jclass methodCls = env->FindClass("java/lang/reflect/Method");
    s_Method_getName = env->GetMethodID(methodCls, "getName", "()Ljava/lang/String;");
    s_Method_getParameterTypes = env->GetMethodID(methodCls, "getParameterTypes", "()[Ljava/lang/Class;");
    s_Method_getReturnType = env->GetMethodID(methodCls, "getReturnType", "()Ljava/lang/Class;");
    env->DeleteLocalRef(methodCls);

    jclass fieldCls = env->FindClass("java/lang/reflect/Field");
    s_Field_getName = env->GetMethodID(fieldCls, "getName", "()Ljava/lang/String;");
    s_Field_getType = env->GetMethodID(fieldCls, "getType", "()Ljava/lang/Class;");
    s_Field_getModifiers = env->GetMethodID(fieldCls, "getModifiers", "()I");
    env->DeleteLocalRef(fieldCls);

    s_reflection_inited = true;
}

//----------------------------------------------------------------------
// Convert a java.lang.Class object to a JNI type signature string
//----------------------------------------------------------------------
static std::string class_to_jni_sig(jclass typeClass) {
    jstring nameStr = (jstring) env->CallObjectMethod(typeClass, s_Class_getName);
    const char *name = env->GetStringUTFChars(nameStr, nullptr);
    std::string result(name);
    env->ReleaseStringUTFChars(nameStr, name);
    env->DeleteLocalRef(nameStr);

    // Primitives
    if (result == "int") return "I";
    if (result == "long") return "J";
    if (result == "float") return "F";
    if (result == "double") return "D";
    if (result == "boolean") return "Z";
    if (result == "byte") return "B";
    if (result == "char") return "C";
    if (result == "short") return "S";
    if (result == "void") return "V";

    // Array types: Class.getName() returns "[Lfoo.Bar;" etc.
    // Convert dots to slashes for object types inside arrays
    for (auto &ch : result) {
        if (ch == '.') ch = '/';
    }
    // Non-array object type: prepend L and append ;
    if (result[0] != '[') {
        result = "L" + result + ";";
    }
    return result;
}

//----------------------------------------------------------------------
// Count JNI type tags in an arg_tags string
//----------------------------------------------------------------------
static int count_tags(const std::string &arg_tags) {
    int n = 0;
    const char *p = arg_tags.c_str();
    while (*p) {
        if (*p == 'L') {
            const char *semi = strchr(p, ';');
            if (!semi) return -1;
            p = semi + 1;
        } else if (*p == '[') {
            while (*p == '[') p++;
            if (*p == 'L') {
                const char *semi = strchr(p, ';');
                if (!semi) return -1;
                p = semi + 1;
            } else {
                p++;
            }
        } else {
            p++;
        }
        n++;
    }
    return n;
}

//----------------------------------------------------------------------
// Step through JNI signature, optionally writing tag to out.
// If out is nullptr, just advances past the tag.
//----------------------------------------------------------------------
static const char *next_tag(const char *p, char *out = nullptr) {
    if (*p == 'L') {
        const char *semi = strchr(p, ';');
        if (!semi) throwError("Invalid JNI object signature");
        size_t n = semi - p + 1;
        if (out) {
            memcpy(out, p, n);
            out[n] = '\0';
        }
        return semi + 1;
    } else if (*p == '[') {
        const char *q = p;
        while (*q == '[') q++;
        if (*q == 'L') {
            const char *semi = strchr(q, ';');
            if (!semi) throwError("Invalid JNI array signature");
            size_t n = semi - p + 1;
            if (out) {
                memcpy(out, p, n);
                out[n] = '\0';
            }
            return semi + 1;
        } else {
            if (out) {
                out[0] = *p;
                out[1] = '\0';
            }
            return p + 1;
        }
    } else {
        if (out) {
            out[0] = *p;
            out[1] = '\0';
        }
        return p + 1;
    }
}

//----------------------------------------------------------------------
// Data structures for cached reflection info
//----------------------------------------------------------------------
struct MethodInfo {
    jmethodID method_id = nullptr;
    std::string arg_tags;   // e.g., "Ljava/lang/String;I"
    std::string return_sig; // e.g., "Ljava/lang/String;"
};

struct FieldInfo {
    jfieldID field_id = nullptr;
    std::string sig;        // e.g., "Ljava/lang/String;" or "I"
    bool is_static = false;
};

//----------------------------------------------------------------------
// Box types: wrap JNI handles and cached reflection data
//----------------------------------------------------------------------
// Box type identifiers (stored in Box::type_info)
enum : uint32_t {
    BOX_JNI_CLASS  = 1,
    BOX_JNI_METHOD = 2,
    BOX_JNI_FIELD  = 3,
    BOX_JNI_OBJECT = 4,
};

class JniClass;

class JniClass : public Box {
public:
    jclass cls;
    std::unordered_map<std::string, std::vector<MethodInfo>> methods;
    std::unordered_map<std::string, FieldInfo> fields;
    std::vector<MethodInfo> constructors;
    bool reflected = false;

    explicit JniClass(jclass c) : cls(c) { type_info = BOX_JNI_CLASS; }
    ~JniClass() override {
        if (cls && env) env->DeleteGlobalRef(cls);
    }

    void reflect();
    const std::vector<MethodInfo> *find_method(const std::string &name);
    const FieldInfo *find_field(const std::string &name);
    const MethodInfo *find_constructor(int nargs);
};

class JniMethod : public Box {
public:
    std::vector<MethodInfo> overloads;

    JniMethod() { type_info = BOX_JNI_METHOD; }

    // Score how well a PHP arg matches a JNI parameter type tag
    static int score_arg_match(const Variant &arg, const char *tag_start) {
        char tag[256];
        next_tag(tag_start, tag);
        switch (tag[0]) {
        case 'L':
            if (strcmp(tag, "Ljava/lang/String;") == 0 && arg.isString()) return 10;
            if (arg.isString()) return 5; // auto-convert string → jstring
            if (arg.isResource()) return 3; // JniObject
            return 1;
        case 'I': case 'J': case 'S': case 'B':
            if (arg.isInt()) return 10;
            if (arg.isFloat()) return 3;
            return 1;
        case 'F': case 'D':
            if (arg.isFloat()) return 10;
            if (arg.isInt()) return 5;
            return 1;
        case 'Z':
            if (arg.isBool()) return 10;
            return 1;
        default:
            return 1;
        }
    }

    const MethodInfo *find_by_arg_count(int nargs, Array &args) const {
        const MethodInfo *best = nullptr;
        int best_score = -1;
        for (auto &m : overloads) {
            if (count_tags(m.arg_tags) != nargs) continue;
            int score = 0;
            const char *p = m.arg_tags.c_str();
            for (int i = 0; i < nargs; i++) {
                score += score_arg_match(args[i], p);
                p = next_tag(p);
            }
            if (score > best_score) {
                best_score = score;
                best = &m;
            }
        }
        return best;
    }
};

class JniField : public Box {
public:
    FieldInfo info;

    JniField() { type_info = BOX_JNI_FIELD; }
};

class JniObject : public Box {
public:
    jobject obj;
    explicit JniObject(jobject o) : obj(o) { type_info = BOX_JNI_OBJECT; }
    ~JniObject() override {
        if (obj && env) env->DeleteGlobalRef(obj);
    }
};

//----------------------------------------------------------------------
// Lazy reflection: populate methods/fields/constructors via Java reflection
//----------------------------------------------------------------------
void JniClass::reflect() {
    if (reflected) return;
    init_reflection();

    // --- Constructors via getDeclaredConstructors() ---
    jobjectArray ctors = (jobjectArray) env->CallObjectMethod(cls, s_Class_getDeclaredConstructors);
    jsize nctors = env->GetArrayLength(ctors);
    for (jsize i = 0; i < nctors; i++) {
        jobject ctor = env->GetObjectArrayElement(ctors, i);
        jobjectArray paramTypes = (jobjectArray) env->CallObjectMethod(ctor, s_Constructor_getParameterTypes);
        jsize nparams = env->GetArrayLength(paramTypes);

        std::string argTags;
        for (jsize j = 0; j < nparams; j++) {
            jclass paramClass = (jclass) env->GetObjectArrayElement(paramTypes, j);
            argTags += class_to_jni_sig(paramClass);
            env->DeleteLocalRef(paramClass);
        }

        std::string fullSig = "(" + argTags + ")V";
        jmethodID methodId = env->GetMethodID(cls, "<init>", fullSig.c_str());
        constructors.push_back({methodId, argTags, "V"});

        env->DeleteLocalRef(paramTypes);
        env->DeleteLocalRef(ctor);
    }
    env->DeleteLocalRef(ctors);

    // If no explicit constructors, add default no-arg constructor
    if (nctors == 0) {
        jmethodID methodId = env->GetMethodID(cls, "<init>", "()V");
        if (!env->ExceptionCheck()) {
            constructors.push_back({methodId, "", "V"});
        } else {
            env->ExceptionClear();
        }
    }

    // --- Methods via getMethods() ---
    jobjectArray methods_arr = (jobjectArray) env->CallObjectMethod(cls, s_Class_getMethods);
    jsize nmethods = env->GetArrayLength(methods_arr);
    for (jsize i = 0; i < nmethods; i++) {
        jobject method = env->GetObjectArrayElement(methods_arr, i);
        jstring nameStr = (jstring) env->CallObjectMethod(method, s_Method_getName);
        const char *nameChars = env->GetStringUTFChars(nameStr, nullptr);
        std::string methodName(nameChars);
        env->ReleaseStringUTFChars(nameStr, nameChars);
        env->DeleteLocalRef(nameStr);

        // Parameter types
        jobjectArray paramTypes = (jobjectArray) env->CallObjectMethod(method, s_Method_getParameterTypes);
        jsize nparams = env->GetArrayLength(paramTypes);

        std::string argTags;
        for (jsize j = 0; j < nparams; j++) {
            jclass paramClass = (jclass) env->GetObjectArrayElement(paramTypes, j);
            argTags += class_to_jni_sig(paramClass);
            env->DeleteLocalRef(paramClass);
        }

        // Return type
        jclass returnClass = (jclass) env->CallObjectMethod(method, s_Method_getReturnType);
        std::string returnSig = class_to_jni_sig(returnClass);
        env->DeleteLocalRef(returnClass);

        std::string fullSig = "(" + argTags + ")" + returnSig;
        jmethodID methodId = env->GetMethodID(cls, methodName.c_str(), fullSig.c_str());

        methods[methodName].push_back({methodId, argTags, returnSig});

        env->DeleteLocalRef(paramTypes);
        env->DeleteLocalRef(method);
    }
    env->DeleteLocalRef(methods_arr);

    // --- Fields via getDeclaredFields() ---
    jobjectArray fields_arr = (jobjectArray) env->CallObjectMethod(cls, s_Class_getDeclaredFields);
    jsize nfields = env->GetArrayLength(fields_arr);
    for (jsize i = 0; i < nfields; i++) {
        jobject field = env->GetObjectArrayElement(fields_arr, i);
        jstring nameStr = (jstring) env->CallObjectMethod(field, s_Field_getName);
        const char *nameChars = env->GetStringUTFChars(nameStr, nullptr);
        std::string fieldName(nameChars);
        env->ReleaseStringUTFChars(nameStr, nameChars);
        env->DeleteLocalRef(nameStr);

        jclass typeClass = (jclass) env->CallObjectMethod(field, s_Field_getType);
        std::string sig = class_to_jni_sig(typeClass);
        env->DeleteLocalRef(typeClass);

        jint mods = env->CallIntMethod(field, s_Field_getModifiers);
        bool isStatic = (mods & 0x0008) != 0; // java.lang.reflect.Modifier.STATIC = 8

        jfieldID fieldId;
        if (isStatic) {
            fieldId = env->GetStaticFieldID(cls, fieldName.c_str(), sig.c_str());
        } else {
            fieldId = env->GetFieldID(cls, fieldName.c_str(), sig.c_str());
        }
        fields[fieldName] = {fieldId, sig, isStatic};

        env->DeleteLocalRef(field);
    }
    env->DeleteLocalRef(fields_arr);

    reflected = true;
}

const std::vector<MethodInfo> *JniClass::find_method(const std::string &name) {
    reflect();
    auto it = methods.find(name);
    return (it != methods.end()) ? &it->second : nullptr;
}

const FieldInfo *JniClass::find_field(const std::string &name) {
    reflect();
    auto it = fields.find(name);
    return (it != fields.end()) ? &it->second : nullptr;
}

const MethodInfo *JniClass::find_constructor(int nargs) {
    reflect();
    for (auto &c : constructors) {
        if (count_tags(c.arg_tags) == nargs) return &c;
    }
    return nullptr;
}

//----------------------------------------------------------------------
// Safe, non-throwing Box extraction with type checking via Box::getTypeInfo()
//----------------------------------------------------------------------
static Box *get_box(const Variant &v) {
    if (!v.isResource()) return nullptr;
    auto *mut = const_cast<Variant *>(&v);
    auto res = Z_RES_P(mut->unwrap_ptr());
    if (res->type != getBoxResourceId()) return nullptr;
    return static_cast<Box *>(res->ptr);
}

template <uint32_t Type>
static inline bool is_box_type(const Variant &v) {
    auto *box = get_box(v);
    return box && box->getTypeInfo() == Type;
}

static inline JniClass *to_jni_class(const Variant &v) {
    auto *box = get_box(v);
    return (box && box->getTypeInfo() == BOX_JNI_CLASS) ? static_cast<JniClass *>(box) : nullptr;
}

static inline JniMethod *to_jni_method(const Variant &v) {
    auto *box = get_box(v);
    return (box && box->getTypeInfo() == BOX_JNI_METHOD) ? static_cast<JniMethod *>(box) : nullptr;
}

static inline JniField *to_jni_field(const Variant &v) {
    auto *box = get_box(v);
    return (box && box->getTypeInfo() == BOX_JNI_FIELD) ? static_cast<JniField *>(box) : nullptr;
}

static inline JniObject *to_jni_object(const Variant &v) {
    auto *box = get_box(v);
    return (box && box->getTypeInfo() == BOX_JNI_OBJECT) ? static_cast<JniObject *>(box) : nullptr;
}

//----------------------------------------------------------------------
// Extract jclass from a Variant (JniClass or JniObject)
//----------------------------------------------------------------------
static jclass resolve_class(const Variant &v) {
    if (auto *jc = to_jni_class(v)) {
        return jc->cls;
    }
    if (auto *jo = to_jni_object(v)) {
        return env->GetObjectClass(jo->obj);
    }
    throwError("Argument must be a JniClass or JniObject");
    return nullptr;
}

// Get the JniClass* from a Variant (JniClass directly, or from JniObject's class)
static JniClass *resolve_jni_class(const Variant &v) {
    if (auto *jc = to_jni_class(v)) {
        return jc;
    }
    throwError("Argument must be a JniClass handle");
    return nullptr;
}

static jobject resolve_object(const Variant &v) {
    if (auto *jo = to_jni_object(v)) {
        return jo->obj;
    }
    throwError("Argument must be a JniObject");
    return nullptr;
}

static bool is_jni_class(const Variant &v) {
    return is_box_type<BOX_JNI_CLASS>(v);
}

//----------------------------------------------------------------------
// Marshal: Variant → jvalue (by single-char JNI type tag)
//----------------------------------------------------------------------
static jvalue marshal_arg(const Variant &arg, char tag) {
    jvalue jv{};
    switch (tag) {
    case 'Z': jv.z = (jboolean) arg.toBool(); break;
    case 'B': jv.b = (jbyte) arg.toInt();    break;
    case 'C': jv.c = (jchar) arg.toInt();    break;
    case 'S': jv.s = (jshort) arg.toInt();   break;
    case 'I': jv.i = (jint) arg.toInt();     break;
    case 'J': jv.j = (jlong) arg.toInt();    break;
    case 'F': jv.f = (jfloat) arg.toFloat(); break;
    case 'D': jv.d = (jdouble) arg.toFloat(); break;
    case 'L':
        if (auto *jo = to_jni_object(arg)) {
            jv.l = jo->obj;
        } else {
            jv.l = env->NewStringUTF(arg.toCString());
        }
        break;
    case '[':
        if (auto *jo = to_jni_object(arg)) {
            jv.l = jo->obj;
        } else {
            throwError("Array argument must be a JniObject");
        }
        break;
    default:
        throwError("Unknown JNI tag: %c", tag);
    }
    return jv;
}

//----------------------------------------------------------------------
// Marshal: JNI return → Variant (by full type signature string)
//----------------------------------------------------------------------
static Variant marshal_return(jvalue jv, const std::string &return_sig) {
    const char *sig = return_sig.c_str();
    switch (sig[0]) {
    case 'V': return nullptr;
    case 'Z': return Variant((bool) jv.z);
    case 'B': return Variant((Int) jv.b);
    case 'C': return Variant((Int) jv.c);
    case 'S': return Variant((Int) jv.s);
    case 'I': return Variant((Int) jv.i);
    case 'J': return Variant((Int) jv.j);
    case 'F': return Variant((double) jv.f);
    case 'D': return Variant(jv.d);
    case 'L':
    case '[': {
        if (!jv.l) return nullptr;
        jclass stringClass = env->FindClass("java/lang/String");
        if (env->IsInstanceOf((jobject) jv.l, stringClass)) {
            auto *jstr = (jstring) jv.l;
            const char *chars = env->GetStringUTFChars(jstr, nullptr);
            String ret(chars);
            env->ReleaseStringUTFChars(jstr, chars);
            env->DeleteLocalRef(stringClass);
            return ret;
        }
        env->DeleteLocalRef(stringClass);
        jobject global = env->NewGlobalRef((jobject) jv.l);
        return {new JniObject(global)};
    }
    default:
        throwError("Unknown JNI return type: %s", sig);
        return {};
    }
}

//----------------------------------------------------------------------
// PHP-callable functions
//----------------------------------------------------------------------

void php_jni_init(String classpath) {
    if (jvm) return;

    JavaVMInitArgs vm_args;
    JavaVMOption options[2];
    std::string cp = "-Djava.class.path=" + std::string(classpath.data(), classpath.length());
    options[0].optionString = cp.data();
    options[1].optionString = const_cast<char *>("-Xcheck:jni");

    vm_args.version = JNI_VERSION_10;
    vm_args.nOptions = 1;
    vm_args.options = options;
    vm_args.ignoreUnrecognized = JNI_TRUE;

    jint rc = JNI_CreateJavaVM(&jvm, (void **) &env, &vm_args);
    if (rc != JNI_OK) {
        throwError("Failed to create JVM, error code: %d", rc);
    }
}

void php_jni_destroy() {
    if (jvm) {
        jvm->DestroyJavaVM();
        jvm = nullptr;
        env = nullptr;
    }
}

/**
 * jni_find_class(string $className): mixed
 * Returns a JniClass handle. Reflection is lazy, triggered on first use.
 */
var php_jni_find_class(String className) {
    if (!jvm) throwError("JNI not initialized, call jni_init() first");

    std::string cn(className.data(), className.length());
    for (auto &ch : cn) {
        if (ch == '.') ch = '/';
    }

    jclass cls = env->FindClass(cn.c_str());
    check_jni_exception();
    if (!cls) {
        throwError("Class not found: %s", className.data());
        return {};
    }
    jclass globalCls = (jclass) env->NewGlobalRef(cls);
    env->DeleteLocalRef(cls);
    return {new JniClass(globalCls)};
}

/**
 * jni_find_method(mixed $objOrClass, string $methodName): mixed
 * Returns a JniMethod handle containing all overloads of the named method.
 */
var php_jni_find_method(var objOrClass, String methodName) {
    auto *jc = resolve_jni_class(objOrClass);
    auto *overloads = jc->find_method(std::string(methodName.data(), methodName.length()));
    if (!overloads || overloads->empty()) {
        throwError("Method not found: %s", methodName.data());
        return {};
    }
    auto *box = new JniMethod();
    box->overloads = *overloads;
    return {box};
}

/**
 * jni_find_field(mixed $objOrClass, string $fieldName): mixed
 * Returns a JniField handle with cached type and field ID.
 */
var php_jni_find_field(var objOrClass, String fieldName) {
    auto *jc = resolve_jni_class(objOrClass);
    auto *info = jc->find_field(std::string(fieldName.data(), fieldName.length()));
    if (!info) {
        throwError("Field not found: %s", fieldName.data());
        return {};
    }
    auto *box = new JniField();
    box->info = *info;
    return {box};
}

/**
 * jni_new_object(mixed $classHandle, array $args = []): mixed
 * Creates a new Java object. Constructor is found automatically by arg count.
 */
var php_jni_new_object(var classHandle, Array args) {
    auto *jc = to_jni_class(classHandle);
    if (!jc) throwError("First argument must be a JniClass handle");

    int nargs = args.count();
    auto *ctorInfo = jc->find_constructor(nargs);
    if (!ctorInfo) {
        throwError("No constructor found with %d argument(s)", nargs);
        return {};
    }

    // Marshal args using cached signature
    std::vector<jvalue> jvals(nargs);
    const char *p = ctorInfo->arg_tags.c_str();
    for (int i = 0; i < nargs; i++) {
        char tag[256];
        p = next_tag(p, tag);
        jvals[i] = marshal_arg(args[i], tag[0]);
    }

    jobject obj = env->NewObjectA(jc->cls, ctorInfo->method_id, jvals.data());
    check_jni_exception();
    if (!obj) {
        throwError("Failed to create object");
        return {};
    }
    jobject globalObj = env->NewGlobalRef(obj);
    env->DeleteLocalRef(obj);
    return {new JniObject(globalObj)};
}

/**
 * jni_call(mixed $objOrClass, mixed $method, array $args = []): mixed
 * Call an instance or static method. $method is a JniMethod handle.
 */
var php_jni_call(var objOrClass, var method, Array args) {
    jclass cls = resolve_class(objOrClass);
    jobject obj = is_jni_class(objOrClass) ? nullptr : resolve_object(objOrClass);

    auto *jm = to_jni_method(method);
    if (!jm) throwError("Second argument must be a JniMethod handle");

    int nargs = args.count();
    auto *methodInfo = jm->find_by_arg_count(nargs, args);
    if (!methodInfo) {
        throwError("No matching overload of method with %d argument(s)", nargs);
        return {};
    }

    // Marshal args
    std::vector<jvalue> jvals(nargs);
    const char *p = methodInfo->arg_tags.c_str();
    for (int i = 0; i < nargs; i++) {
        char tag[256];
        p = next_tag(p, tag);
        jvals[i] = marshal_arg(args[i], tag[0]);
    }

    bool isStatic = (obj == nullptr);
    jvalue ret{};
    const char *retSig = methodInfo->return_sig.c_str();

    switch (retSig[0]) {
    case 'V':
        if (isStatic) {
            env->CallStaticVoidMethodA(cls, methodInfo->method_id, jvals.data());
        } else {
            env->CallVoidMethodA(obj, methodInfo->method_id, jvals.data());
        }
        check_jni_exception();
        if (!isStatic) env->DeleteLocalRef(cls);
        return nullptr;
    case 'Z':
        ret.z = isStatic ? env->CallStaticBooleanMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallBooleanMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'B':
        ret.b = isStatic ? env->CallStaticByteMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallByteMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'C':
        ret.c = isStatic ? env->CallStaticCharMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallCharMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'S':
        ret.s = isStatic ? env->CallStaticShortMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallShortMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'I':
        ret.i = isStatic ? env->CallStaticIntMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallIntMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'J':
        ret.j = isStatic ? env->CallStaticLongMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallLongMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'F':
        ret.f = isStatic ? env->CallStaticFloatMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallFloatMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'D':
        ret.d = isStatic ? env->CallStaticDoubleMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallDoubleMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    case 'L':
    case '[':
        ret.l = isStatic ? env->CallStaticObjectMethodA(cls, methodInfo->method_id, jvals.data())
                         : env->CallObjectMethodA(obj, methodInfo->method_id, jvals.data());
        break;
    default:
        if (!isStatic) env->DeleteLocalRef(cls);
        throwError("Unknown JNI return type: %s", retSig);
        return {};
    }
    check_jni_exception();

    if (!isStatic) {
        env->DeleteLocalRef(cls);
    }
    return marshal_return(ret, methodInfo->return_sig);
}

/**
 * jni_get(mixed $objOrClass, mixed $field): mixed
 * Read an instance or static field. $field is a JniField handle.
 */
var php_jni_get(var objOrClass, var field) {
    jclass cls = resolve_class(objOrClass);
    jobject obj = is_jni_class(objOrClass) ? nullptr : resolve_object(objOrClass);

    auto *jf = to_jni_field(field);
    if (!jf) throwError("Field argument must be a JniField handle");

    jvalue jv{};
    const char *s = jf->info.sig.c_str();

    if (jf->info.is_static) {
        switch (s[0]) {
        case 'Z': jv.z = env->GetStaticBooleanField(cls, jf->info.field_id); break;
        case 'B': jv.b = env->GetStaticByteField(cls, jf->info.field_id);    break;
        case 'C': jv.c = env->GetStaticCharField(cls, jf->info.field_id);    break;
        case 'S': jv.s = env->GetStaticShortField(cls, jf->info.field_id);   break;
        case 'I': jv.i = env->GetStaticIntField(cls, jf->info.field_id);     break;
        case 'J': jv.j = env->GetStaticLongField(cls, jf->info.field_id);    break;
        case 'F': jv.f = env->GetStaticFloatField(cls, jf->info.field_id);   break;
        case 'D': jv.d = env->GetStaticDoubleField(cls, jf->info.field_id);  break;
        case 'L': case '[': jv.l = env->GetStaticObjectField(cls, jf->info.field_id); break;
        default: throwError("Unknown field type: %s", s);
        }
    } else {
        switch (s[0]) {
        case 'Z': jv.z = env->GetBooleanField(obj, jf->info.field_id); break;
        case 'B': jv.b = env->GetByteField(obj, jf->info.field_id);    break;
        case 'C': jv.c = env->GetCharField(obj, jf->info.field_id);    break;
        case 'S': jv.s = env->GetShortField(obj, jf->info.field_id);   break;
        case 'I': jv.i = env->GetIntField(obj, jf->info.field_id);     break;
        case 'J': jv.j = env->GetLongField(obj, jf->info.field_id);    break;
        case 'F': jv.f = env->GetFloatField(obj, jf->info.field_id);   break;
        case 'D': jv.d = env->GetDoubleField(obj, jf->info.field_id);  break;
        case 'L': case '[': jv.l = env->GetObjectField(obj, jf->info.field_id); break;
        default: throwError("Unknown field type: %s", s);
        }
    }
    check_jni_exception();

    if (!is_jni_class(objOrClass)) {
        env->DeleteLocalRef(cls);
    }
    return marshal_return(jv, jf->info.sig);
}

/**
 * jni_set(mixed $objOrClass, mixed $field, mixed $value): void
 * Write an instance or static field. $field is a JniField handle.
 */
void php_jni_set(var objOrClass, var field, var value) {
    jclass cls = resolve_class(objOrClass);
    jobject obj = is_jni_class(objOrClass) ? nullptr : resolve_object(objOrClass);

    auto *jf = to_jni_field(field);
    if (!jf) throwError("Field argument must be a JniField handle");

    jvalue jv = marshal_arg(value, jf->info.sig[0]);
    const char *s = jf->info.sig.c_str();

    if (jf->info.is_static) {
        switch (s[0]) {
        case 'Z': env->SetStaticBooleanField(cls, jf->info.field_id, jv.z); break;
        case 'B': env->SetStaticByteField(cls, jf->info.field_id, jv.b);    break;
        case 'C': env->SetStaticCharField(cls, jf->info.field_id, jv.c);    break;
        case 'S': env->SetStaticShortField(cls, jf->info.field_id, jv.s);   break;
        case 'I': env->SetStaticIntField(cls, jf->info.field_id, jv.i);     break;
        case 'J': env->SetStaticLongField(cls, jf->info.field_id, jv.j);    break;
        case 'F': env->SetStaticFloatField(cls, jf->info.field_id, jv.f);   break;
        case 'D': env->SetStaticDoubleField(cls, jf->info.field_id, jv.d);  break;
        case 'L': case '[': env->SetStaticObjectField(cls, jf->info.field_id, (jobject) jv.l); break;
        default: throwError("Unknown field type: %s", s);
        }
    } else {
        switch (s[0]) {
        case 'Z': env->SetBooleanField(obj, jf->info.field_id, jv.z); break;
        case 'B': env->SetByteField(obj, jf->info.field_id, jv.b);    break;
        case 'C': env->SetCharField(obj, jf->info.field_id, jv.c);    break;
        case 'S': env->SetShortField(obj, jf->info.field_id, jv.s);   break;
        case 'I': env->SetIntField(obj, jf->info.field_id, jv.i);     break;
        case 'J': env->SetLongField(obj, jf->info.field_id, jv.j);    break;
        case 'F': env->SetFloatField(obj, jf->info.field_id, jv.f);   break;
        case 'D': env->SetDoubleField(obj, jf->info.field_id, jv.d);  break;
        case 'L': case '[': env->SetObjectField(obj, jf->info.field_id, (jobject) jv.l); break;
        default: throwError("Unknown field type: %s", s);
        }
    }
    check_jni_exception();

    if (!is_jni_class(objOrClass)) {
        env->DeleteLocalRef(cls);
    }
}
