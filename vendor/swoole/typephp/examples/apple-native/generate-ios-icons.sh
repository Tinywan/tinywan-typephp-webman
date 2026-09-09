#!/bin/sh

set -eu

project_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
source_icon="$project_dir/ios-assets/AppIcon.svg"
logo_source="$project_dir/../../swoole-logo.svg"
output_dir="$project_dir/ios-assets"
master_icon="$output_dir/AppIcon-1024.png"
temporary_dir=$(mktemp -d "${TMPDIR:-/tmp}/typephp-ios-icon.XXXXXX")
temporary_logo="$temporary_dir/swoole-logo.svg"
trap 'rm -rf "$temporary_dir"' EXIT HUP INT TERM

if ! command -v ffmpeg >/dev/null 2>&1; then
    echo "FFmpeg is required to render the SVG app icon." >&2
    exit 1
fi

# Override only the SVG viewport size. Its vector paths remain unchanged and
# are rasterized directly at the size used by the 1024px master artwork.
sed 's/width="100px" height="60px"/width="820px" height="282px"/' \
    "$logo_source" > "$temporary_logo"

ffmpeg -hide_banner -loglevel error -y \
    -i "$source_icon" \
    -i "$temporary_logo" \
    -filter_complex '[0:v][1:v]overlay=102:371:format=auto,format=rgb24' \
    -frames:v 1 \
    "$master_icon"

for size in 40 58 60 80 87 120 180; do
    ffmpeg -hide_banner -loglevel error -y \
        -i "$master_icon" \
        -vf "scale=${size}:${size}:flags=lanczos" \
        -frames:v 1 -pix_fmt rgb24 \
        "$output_dir/AppIcon-${size}.png"
done

cp "$output_dir/AppIcon-120.png" "$output_dir/AppIcon60x60@2x.png"
cp "$output_dir/AppIcon-180.png" "$output_dir/AppIcon60x60@3x.png"

echo "Generated iOS icons in $output_dir"
