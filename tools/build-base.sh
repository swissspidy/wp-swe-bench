#!/usr/bin/env bash
# Build the shared base image. Usage: tools/build-base.sh [tag]
# Behind a TLS-intercepting proxy, set WPSB_EXTRA_CA_CERT_FILE to a PEM file; proxy env vars
# (HTTPS_PROXY/NO_PROXY) are forwarded automatically and the build uses the host network.
set -euo pipefail
cd "$(dirname "$0")/.."
TAG="${1:-$(cat base/VERSION)}"
IMAGE="ghcr.io/swissspidy/wp-swe-bench-base:${TAG}"
args=()
rm -f base/certs/*.crt
if [ -n "${WPSB_EXTRA_CA_CERT_FILE:-}" ]; then
  awk '/BEGIN CERTIFICATE/{n++} n{print > ("base/certs/extra-" n ".crt")}' "$WPSB_EXTRA_CA_CERT_FILE"
fi
trap 'rm -f base/certs/*.crt' EXIT
if [ -n "${WPSB_OS_IMAGE:-}" ]; then args+=(--build-arg "OS_IMAGE=$WPSB_OS_IMAGE"); fi
for v in HTTPS_PROXY https_proxy NO_PROXY no_proxy; do
  if [ -n "${!v:-}" ]; then args+=(--build-arg "$v=${!v}"); fi
done
docker build --network host --build-arg BUILDKIT_INLINE_CACHE=1 "${args[@]}" -t "$IMAGE" base/
echo "built $IMAGE"
