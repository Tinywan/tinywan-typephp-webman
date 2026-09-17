#include <php.h>
#include <zend_gc.h>

unsigned long physical_chunk_smoke_test(void)
{

    unsigned char *small = (unsigned char *) emalloc(37);
    unsigned char *large = (unsigned char *) emalloc(96 * 1024);
    if (small == 0 || large == 0 || small == large) {
        return 0;
    }

    for (unsigned long index = 0; index < 37; ++index) {
        small[index] = (unsigned char) (index + 1);
    }
    for (unsigned long index = 0; index < 37; ++index) {
        if (small[index] != (unsigned char) (index + 1)) {
            return 0;
        }
    }

    efree(large);
    efree(small);

    if (!gc_enabled()) {
        return 0;
    }
    zend_gc_status status;
    zend_gc_get_status(&status);
    /* A newly enabled collector has an allocated, empty root buffer. */
    if (status.runs != 0 || status.collected != 0 || status.num_roots != 0) {
        return 0;
    }

    zend_string *key = zend_string_init("answer", sizeof("answer") - 1, 0);
    if (key == 0 || ZSTR_LEN(key) != sizeof("answer") - 1
        || memcmp(ZSTR_VAL(key), "answer", sizeof("answer")) != 0) {
        return 0;
    }

    HashTable table;
    zend_hash_init(&table, 4, 0, 0, 0);
    zval value;
    ZVAL_STRING(&value, "forty-two");
    if (zend_hash_add(&table, key, &value) == 0) {
        return 0;
    }
    zval *found = zend_hash_find(&table, key);
    if (found == 0 || Z_TYPE_P(found) != IS_STRING
        || !zend_string_equals_literal(Z_STR_P(found), "forty-two")) {
        return 0;
    }
    zend_hash_destroy(&table);
    zend_string_release(key);

    return zend_memory_usage(1) >= 2ul * 1024ul * 1024ul ? 2 : 0;
}
