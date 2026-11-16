#!/usr/bin/env bash
# Installs a packed @mgamadeus/ddd-site-insights tarball into a fresh consumer project and checks it as a user would:
#   1. every exports target exists in the tarball and entry `.` is free of React, Redux runtime and stylesheets
#      (scripts/check-package.mjs)
#   2. entry `.` imports at runtime (smoke.mjs)
#   3. a strict TypeScript consumer of entry `.` compiles with moduleResolution bundler and nodenext
#
# Usage: test/consumer/run.sh <tarball> <npm|pnpm> <workDir>
set -euo pipefail

TARBALL=$(cd "$(dirname "$1")" && pwd)/$(basename "$1")
PM=$2
WORK=$3
HERE=$(cd "$(dirname "$0")" && pwd)
ROOT=$(cd "$HERE/../.." && pwd)

rm -rf "$WORK" && mkdir -p "$WORK/extracted"
tar -xzf "$TARBALL" -C "$WORK/extracted"
echo "── tarball contents"
node "$ROOT/scripts/check-package.mjs" "$WORK/extracted/package"

APP="$WORK/app"
mkdir -p "$APP"
cp "$HERE/smoke.mjs" "$HERE/consumer.ts" "$APP/"
cd "$APP"
echo '{"name":"consumer","private":true,"type":"module"}' > package.json
# DDD_WEB_VERSION: the @mgamadeus/ddd-web version to check against (default: the latest; the peer range starts at 0.13.0)
DEPS=("$TARBALL" "@mgamadeus/ddd-web@${DDD_WEB_VERSION:-latest}" @reduxjs/toolkit react react-redux typescript@^6 @types/node)
echo "── install with $PM"
if [ "$PM" = npm ]; then npm install --no-audit --no-fund "${DEPS[@]}"; else pnpm add "${DEPS[@]}"; fi

echo "── runtime import"
node smoke.mjs

for MODE in bundler nodenext; do
  if [ "$MODE" = bundler ]; then MODULE=esnext; else MODULE=nodenext; fi
  cat > "tsconfig.$MODE.json" <<JSON
{
  "compilerOptions": {
    "target": "ES2022", "lib": ["ES2022"], "module": "$MODULE", "moduleResolution": "$MODE",
    "strict": true, "skipLibCheck": false, "noEmit": true, "types": ["node"]
  },
  "include": ["consumer.ts"]
}
JSON
  echo "── type check ($MODE)"
  ./node_modules/.bin/tsc -p "tsconfig.$MODE.json"
done
echo "── consumer check passed ($PM)"
