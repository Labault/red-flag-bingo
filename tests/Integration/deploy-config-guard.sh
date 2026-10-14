#!/usr/bin/env bash
set -euo pipefail

root=$(cd "$(dirname "$0")/../.." && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
mkdir -p "$work/bin"
cp "${RFB_DEPLOY_SCRIPT:-$root/deploy.sh}" "$work/deploy.sh"
touch "$work/.env.prod.local"

cat >"$work/bin/docker" <<'DOCKER'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$RFB_DEPLOY_TRACE"
if [ run = "$1" ]; then
    exit "$RFB_VALIDATION_EXIT"
fi
DOCKER
chmod +x "$work/bin/docker"

export PATH="$work/bin:$PATH"
export RFB_DEPLOY_TRACE="$work/trace"
export RFB_VALIDATION_EXIT=12
if bash "$work/deploy.sh" >"$work/failure.log" 2>&1; then
    echo 'FAIL: an invalid runtime configuration did not stop deployment.' >&2
    exit 1
fi
if grep -Eq 'doctrine:migrations:migrate| up ' "$work/trace"; then
    echo 'FAIL: invalid runtime configuration reached migrations or replacement.' >&2
    exit 1
fi

: >"$work/trace"
export RFB_VALIDATION_EXIT=0
bash "$work/deploy.sh" >"$work/success.log" 2>&1
grep -q doctrine:migrations:migrate "$work/trace"
grep -q ' up ' "$work/trace"
echo 'PASS: invalid config stops before mutations; valid config continues deployment.'
