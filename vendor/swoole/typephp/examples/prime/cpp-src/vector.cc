#include <phpx.h>

using namespace php;

class VectorBox : public Box {
  public:
    std::vector<bool> vec;
    VectorBox(size_t size, bool init) {
        vec.resize(size, init);
    }
    void checkOffset(Int offset) {
        if (offset >= vec.size()) {
            zend_throw_error(NULL, "index[%ld] is out of range()", offset);
        }
    }
};

var php_vector_new(Int size, Bool init) {
    return {new VectorBox(size, init)};
}

Bool php_vector_get(var box, Int offset) {
    auto vecbox = box.toBox<VectorBox>();
    vecbox->checkOffset(offset);
    return vecbox->vec.at(offset);
}

void php_vector_set(var box, Int offset, Bool value) {
    auto vecbox = box.toBox<VectorBox>();
    vecbox->checkOffset(offset);
    vecbox->vec.at(offset) = value;
}
