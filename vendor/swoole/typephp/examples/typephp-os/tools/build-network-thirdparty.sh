#!/usr/bin/env bash

set -euo pipefail

project_dir=$(cd -- "$(dirname -- "$0")/.." && pwd)
build_root="${project_dir}/build/network-thirdparty"
install_root="${build_root}/install"
jobs=${JOBS:-4}
build_revision=5
build_stamp="${build_root}/.typephp-os-network-v${build_revision}"
common_cflags="-O2 -U_FORTIFY_SOURCE -D_FORTIFY_SOURCE=0 -ffreestanding -fno-builtin -fno-stack-protector -fno-pic -fno-pie -ffunction-sections -fdata-sections"

"${project_dir}/tools/fetch-openssl.sh"
"${project_dir}/tools/fetch-nghttp2.sh"
"${project_dir}/tools/fetch-curl.sh"

mkdir -p "${build_root}/openssl" "${build_root}/nghttp2" \
    "${build_root}/curl" "${install_root}"

# Configure probes describe the build host, not TypePHP-OS.  Reconfigure all
# three libraries together whenever the freestanding profile changes so stale
# hosted objects can never leak glibc fortify, pthread, or signal dependencies
# into a user-mode ELF.
if [[ ! -f "${build_stamp}" ]]; then
    if [[ -f "${build_root}/openssl/Makefile" ]]; then
        make -C "${build_root}/openssl" clean
    fi
    if [[ -f "${build_root}/nghttp2/Makefile" ]]; then
        make -C "${build_root}/nghttp2" clean
    fi
    if [[ -f "${build_root}/curl/Makefile" ]]; then
        make -C "${build_root}/curl" clean
    fi
    find "${install_root}" -type f -delete
fi

# TLS 1.2/1.3 and X.509 remain complete. Command-line tools, tests, dynamic
# loading, historical protocol versions, datagram TLS, and unrelated protocol
# facilities are excluded from the static TypePHP-OS network SDK.
if [[ ! -f "${install_root}/lib64/libssl.a" \
      && ! -f "${install_root}/lib/libssl.a" ]]; then
    cd "${build_root}/openssl"
    "${project_dir}/thirdparty/openssl/Configure" linux-x86_64 \
        --prefix="${install_root}" --libdir=lib \
        no-shared no-apps no-tests no-docs no-dso no-module no-engine \
        no-legacy no-quic no-dtls no-dtls1 no-dtls1_2 \
        no-ssl3 no-tls1 no-tls1_1 no-cmp no-ocsp no-srp no-ts \
        no-threads no-ui-console \
        -DOPENSSL_RAND_SEED_DEVRANDOM_SHM_ID=-1 \
        ${common_cflags}
    make -j"${jobs}" build_sw
    make install_sw
fi

if [[ ! -f "${install_root}/lib/libnghttp2.a" ]]; then
    cd "${build_root}/nghttp2"
    "${project_dir}/thirdparty/nghttp2/configure" \
        --prefix="${install_root}" --libdir="${install_root}/lib" \
        --disable-shared --enable-static --enable-lib-only \
        --disable-threads --disable-failmalloc \
        CFLAGS="${common_cflags}" LDFLAGS="-no-pie"
    make -j"${jobs}"
    make install
fi

if [[ ! -f "${install_root}/lib/libcurl.a" ]]; then
    cd "${build_root}/curl"
    PKG_CONFIG_PATH="${install_root}/lib/pkgconfig" \
    "${project_dir}/thirdparty/curl/configure" \
        --prefix="${install_root}" --libdir="${install_root}/lib" \
        --disable-shared --enable-static --enable-symbol-hiding \
        --enable-http \
        --disable-ftp --disable-file --disable-ipfs \
        --disable-ldap --disable-ldaps --disable-rtsp \
        --disable-dict --disable-telnet --disable-tftp \
        --disable-pop3 --disable-imap --disable-smb --disable-smtp \
        --disable-gopher --disable-mqtt \
        --disable-proxy --disable-doh --disable-websockets \
        --disable-unix-sockets --disable-socketpair \
        --disable-httpsrr --disable-ech --disable-proxy-http3 \
        --disable-alt-svc --disable-hsts --disable-netrc \
        --disable-manual --disable-docs --disable-libcurl-option \
        --disable-cookies --disable-dateparse --disable-progress-meter \
        --disable-get-easy-options --disable-headers-api --disable-verbose \
        --disable-threaded-resolver --disable-ipv6 \
        --with-openssl="${install_root}" \
        --with-nghttp2="${install_root}" \
        --without-zlib --without-brotli --without-zstd \
        --without-libpsl --without-libidn2 --without-libssh2 \
        --without-librtmp --without-gssapi \
        ac_cv_header_pthread_h=no ac_cv_func_pthread_create=no \
        ac_cv_func_alarm=no ac_cv_func_sigaction=no \
        curl_cv_func_alarm=no curl_cv_func_sigaction=no \
        curl_cv_func_signal=no \
        CFLAGS="${common_cflags}" \
        LDFLAGS="-no-pie -Wl,--gc-sections"
    # Autoconf executed on Linux reports signal APIs that TypePHP-OS
    # deliberately does not expose. CURLOPT_NOSIGNAL is always used by the
    # user runtime, and removing these generated feature defines also removes
    # unreachable alarm/sigsetjmp ABI dependencies from libcurl.
    sed -i \
        -e 's/^#define HAVE_ALARM 1$/\/\* #undef HAVE_ALARM \*\//' \
        -e 's/^#define HAVE_SIGACTION 1$/\/\* #undef HAVE_SIGACTION \*\//' \
        -e 's/^#define HAVE_SIGNAL 1$/\/\* #undef HAVE_SIGNAL \*\//' \
        "${build_root}/curl/lib/curl_config.h"
    # Build libcurl only. The curl command-line executable is intentionally
    # outside the TypePHP-OS SDK.
    make -C lib -j"${jobs}"
    make -C lib install
    make -C include install
    mkdir -p "${install_root}/bin" "${install_root}/lib/pkgconfig"
    install -m 755 curl-config "${install_root}/bin/curl-config"
    install -m 644 libcurl.pc "${install_root}/lib/pkgconfig/libcurl.pc"
fi

touch "${build_stamp}"

protocols=$("${build_root}/curl/curl-config" --protocols \
    | tr '[:upper:]' '[:lower:]' | sort -u | tr '\n' ' ')
if [[ "${protocols}" != "http https " ]]; then
    echo "unexpected libcurl protocol set: ${protocols}" >&2
    exit 1
fi

features=$("${build_root}/curl/curl-config" --features | tr '\n' ' ')
if [[ " ${features} " != *" HTTP2 "* ]]; then
    echo "minimal libcurl was built without HTTP/2: ${features}" >&2
    exit 1
fi

echo "OpenSSL static TLS library: OK"
echo "nghttp2 static library: OK"
echo "libcurl protocols: HTTP HTTPS"
echo "libcurl HTTP/2: OK"
echo "Network SDK prefix: ${install_root}"
