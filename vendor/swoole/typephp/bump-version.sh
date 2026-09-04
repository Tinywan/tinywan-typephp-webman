#!/usr/bin/env bash
#
# 更新 TypePHP 编译器版本号。
#
# 用法:
#   ./bump-version.sh                    自动递增修订号（0.6.7 -> 0.6.8）
#   ./bump-version.sh 0.7.0              指定版本
#   ./bump-version.sh patch|minor|major  按语义化版本递增
#   ./bump-version.sh --dry-run [...]    只预览改动，不写文件
#
# 同步修改以下位置：
#   project.yml          version / file-version / product-version
#   src/Translator.php   VERSION 常量

set -euo pipefail

cd "$(dirname "$0")"

PROJECT_YML="project.yml"
TRANSLATOR="src/Translator.php"

DRY_RUN=0
ARG=""

for a in "$@"; do
    case "$a" in
        --dry-run|-n) DRY_RUN=1 ;;
        -h|--help)
            sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) ARG="$a" ;;
    esac
done

die() { printf '错误: %s\n' "$1" >&2; exit 1; }

[[ -f "$PROJECT_YML" ]] || die "找不到 $PROJECT_YML（请在 compiler/ 目录下运行）"
[[ -f "$TRANSLATOR" ]] || die "找不到 $TRANSLATOR（请在 compiler/ 目录下运行）"

# ---------- 读取当前版本 ----------

CUR_YML="$(sed -nE 's/^version: ([0-9]+\.[0-9]+\.[0-9]+)[[:space:]]*$/\1/p' "$PROJECT_YML")"
CUR_PHP="$(sed -nE "s/^[[:space:]]*public const string VERSION = '([0-9]+\.[0-9]+\.[0-9]+)';[[:space:]]*$/\1/p" "$TRANSLATOR")"
BUILD="$(sed -nE 's/^[[:space:]]+file-version: [0-9]+\.[0-9]+\.[0-9]+\.([0-9]+)[[:space:]]*$/\1/p' "$PROJECT_YML")"

[[ -n "$CUR_YML" ]] || die "无法从 $PROJECT_YML 读取 version"
[[ -n "$CUR_PHP" ]] || die "无法从 $TRANSLATOR 读取 VERSION 常量"
[[ -n "$BUILD" ]]   || die "无法从 $PROJECT_YML 读取 file-version 的第四段（构建号）"
[[ "$CUR_YML" == "$CUR_PHP" ]] || die "版本不一致：$PROJECT_YML 为 $CUR_YML，$TRANSLATOR 为 $CUR_PHP"

# ---------- 计算新版本 ----------

bump() {
    local major="${CUR_YML%%.*}"
    local rest="${CUR_YML#*.}"
    local minor="${rest%%.*}"
    local patch="${rest#*.}"
    case "$1" in
        major) echo "$((major + 1)).0.0" ;;
        minor) echo "${major}.$((minor + 1)).0" ;;
        patch) echo "${major}.${minor}.$((patch + 1))" ;;
    esac
}

if [[ -z "$ARG" || "$ARG" == "patch" ]]; then
    NEW="$(bump patch)"
elif [[ "$ARG" == "minor" ]]; then
    NEW="$(bump minor)"
elif [[ "$ARG" == "major" ]]; then
    NEW="$(bump major)"
else
    NEW="$ARG"
    [[ "$NEW" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "版本号格式应为 X.Y.Z，收到：$NEW"
fi

[[ "$NEW" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "计算出的版本号非法：$NEW"

# 新版本必须大于当前版本
if [[ "$NEW" != "$CUR_YML" ]]; then
    lower="$(printf '%s\n%s\n' "$CUR_YML" "$NEW" | sort -V | head -n1)"
    [[ "$lower" == "$CUR_YML" ]] || die "新版本 $NEW 必须大于当前版本 $CUR_YML"
else
    die "新版本与当前版本相同（$CUR_YML），无需更新"
fi

# ---------- 执行替换 ----------

# pattern 必须恰好匹配 1 处，否则中止，避免误改
replace_once() {
    local file="$1" pattern="$2" replacement="$3" label="$4" count
    count="$(grep -cE "$pattern" "$file" || true)"
    [[ "$count" == "1" ]] || die "${label}：在 ${file} 中匹配到 ${count} 处（期望 1 处），已中止，未修改任何文件"
    if [[ "$DRY_RUN" == "1" ]]; then
        # 真实执行一次替换后展示，保证预览与实际写入完全一致
        local before after
        before="$(grep -E "$pattern" "$file")"
        after="$(printf '%s\n' "$before" | sed -E "s|$pattern|$replacement|")"
        printf '  %s\n      - %s\n      + %s\n' "$file" "$before" "$after"
    else
        sed -i -E "s|$pattern|$replacement|" "$file"
    fi
}

if [[ "$DRY_RUN" == "1" ]]; then
    printf '\033[33m[预览模式] 不会写入文件\033[0m\n\n'
fi

printf 'TypePHP %s -> %s\n\n' "$CUR_YML" "$NEW"

replace_once "$PROJECT_YML" \
    "^version: ${CUR_YML//./\\.}[[:space:]]*$" \
    "version: $NEW" \
    "version"

replace_once "$PROJECT_YML" \
    "^([[:space:]]+file-version: )${CUR_YML//./\\.}\\.${BUILD}[[:space:]]*$" \
    "\\1$NEW.$BUILD" \
    "file-version"

replace_once "$PROJECT_YML" \
    "^([[:space:]]+product-version: )${CUR_YML//./\\.}[[:space:]]*$" \
    "\\1$NEW" \
    "product-version"

replace_once "$TRANSLATOR" \
    "^([[:space:]]*public const string VERSION = ')${CUR_YML//./\\.}(';[[:space:]]*)$" \
    "\\1$NEW\\2" \
    "VERSION 常量"

if [[ "$DRY_RUN" == "1" ]]; then
    printf '\n\033[33m预览结束，未写入任何文件。去掉 --dry-run 以实际执行。\033[0m\n'
    exit 0
fi

# ---------- 校验结果 ----------

NEW_YML="$(sed -nE 's/^version: ([0-9]+\.[0-9]+\.[0-9]+)[[:space:]]*$/\1/p' "$PROJECT_YML")"
NEW_PHP="$(sed -nE "s/^[[:space:]]*public const string VERSION = '([0-9]+\.[0-9]+\.[0-9]+)';[[:space:]]*$/\1/p" "$TRANSLATOR")"
[[ "$NEW_YML" == "$NEW" && "$NEW_PHP" == "$NEW" ]] || die "写入后校验失败：yml=$NEW_YML php=$NEW_PHP"

printf '\n\033[32m已更新到 %s\033[0m\n' "$NEW"
printf '  %s: version=%s file-version=%s.%s product-version=%s\n' \
    "$PROJECT_YML" "$NEW" "$NEW" "$BUILD" "$NEW"
printf '  %s: VERSION=%s\n' "$TRANSLATOR" "$NEW"

printf '\n提示：\n'
printf '  1. 编译后 tpc --version 才会显示新版本：./tpc project.yml -O2\n'
printf '  2. 提交：git commit -am "chore(project): bump version to %s"\n' "$NEW"
