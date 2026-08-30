import sys

if sys.version_info < (3, 8):
    print("此脚本需要 Python 3.8 或更高版本")
    sys.exit(1)
else:
    print(f"当前 Python 版本: {sys.version_info.major}.{sys.version_info.minor}.{sys.version_info.micro}")

