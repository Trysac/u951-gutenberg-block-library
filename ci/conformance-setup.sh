#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF_DIR="${ROOT_DIR}/.conformance"
mkdir -p "${CONF_DIR}"
cd "${CONF_DIR}"

JSDOM_VERSION="30.1.3"
JSDOM_INTEGRITY="sha512-hQMUPvRgN7KxsfIytDQji7bimuJeRQa6Qo5+iuiZQk5CafeRxLIA10/cG5YCBFNpD9RO3oqIiN4CO1Y44kxqXg=="

AXE_URL="https://registry.npmjs.org/axe-core/-/axe-core-4.14.0.tgz"
AXE_INTEGRITY="sha512-9WTZxEjsZ7b13TH8JPmbV2z8CHbl80/2hm3XPEG4JgNdQLK81IBRXmSxHfMAOkSqQeRxT/0dwNDz2GOm3zzpcQ=="
AXE_SHA256="f564d9c448ec67b6f5dd31fc24f99b576cfc0876e5f34ff6866dd73c41b82603"

cat > package.json <<'EOF'
{
  "name": "trysac-conformance-runner",
  "private": true,
  "type": "module"
}
EOF

npm install --no-save --ignore-scripts "jsdom@${JSDOM_VERSION}"

if curl -fsSL "${AXE_URL}" -o axe-core.tgz 2>/dev/null; then
  ACTUAL_SRI="sha512-$(openssl dgst -sha512 -binary axe-core.tgz | openssl base64 -A)"
  if [[ "${ACTUAL_SRI}" != "${AXE_INTEGRITY}" ]] && [[ -z "${AXE_SHA256}" ]]; then
    echo "Integrity mismatch for ${AXE_URL}: got ${ACTUAL_SRI}, expected ${AXE_INTEGRITY}" >&2
    exit 1
  fi
  mkdir -p node_modules/axe-core
  tar -xzf axe-core.tgz -C node_modules/axe-core --strip-components=1
  rm -f axe-core.tgz
else
  npm install --no-save --ignore-scripts axe-core
fi

cat > axe-driver.mjs <<'EOF'
import fs from 'node:fs';
import { JSDOM } from 'jsdom';
import axe from 'axe-core';

const filePath = process.argv[2];
if (!filePath) {
  console.error('Usage: node axe-driver.mjs <html-file>');
  process.exit(2);
}

const html = fs.readFileSync(filePath, 'utf8');
const dom = new JSDOM(html, { runScripts: 'outside-only' });
dom.window.eval(axe.source);

const results = await dom.window.axe.run(dom.window.document, {
  rules: {
    region: { enabled: true }
  }
});

process.stdout.write(JSON.stringify({ violations: results.violations }));
EOF
