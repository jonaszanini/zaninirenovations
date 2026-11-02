'use strict';

const assert = require('assert');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const BRIDGE = path.join(ROOT, 'public/js/openplan3d-editor-bridge.js');
const INDEX = path.join(ROOT, 'public/vendor/openplan3d/editor/index.html');
const HTACCESS = path.join(ROOT, 'public/.htaccess');
const EMBED = path.join(ROOT, 'tests/js/fixtures/openplan3d-editor-embed.html');

function bridgeContentVersion() {
  const data = fs.readFileSync(BRIDGE);
  return crypto.createHash('sha256').update(data).digest('hex').slice(0, 16);
}

function assertVersionedBridgeSrc(html, label) {
  assert.doesNotMatch(
    html,
    /openplan3d-editor-bridge\.js["'\s>]/,
    `${label} must not load the bridge without a ?v= cache-bust query`
  );
  const expected = bridgeContentVersion();
  const pattern = new RegExp(`openplan3d-editor-bridge\\.js\\?v=${expected}`);
  assert.match(html, pattern, `${label} bridge ?v= must match current bridge content hash (${expected})`);
  return expected;
}

const htaccess = fs.readFileSync(HTACCESS, 'utf8');
assert.match(htaccess, /SetEnvIf Query_String.*v=/, '.htaccess must treat ?v= as versioned assets');
assert.match(htaccess, /max-age=31536000, immutable.*asset_versioned/s, '.htaccess must long-cache versioned JS/CSS');
assert.match(htaccess, /must-revalidate.*!asset_versioned/s, '.htaccess must not immutable-cache unversioned JS/CSS');
assert.match(htaccess, /_app\/immutable/, '.htaccess must long-cache SvelteKit hashed chunks');

const bridgeSrc = fs.readFileSync(BRIDGE, 'utf8');
assert.match(
  bridgeSrc,
  /contains\('zanini-hosted-openplan3d'\)/,
  'bridge must include #108 hosted-class guard (stale cached pre-#108 bridge caused mutation loop hang)'
);
const applyEditorThemeBlock = bridgeSrc.match(/function applyEditorTheme\(theme\) \{([\s\S]*?)\n  \}/);
assert.ok(applyEditorThemeBlock, 'applyEditorTheme must exist');
assert.doesNotMatch(
  applyEditorThemeBlock[1],
  /localStorage/,
  'applyEditorTheme must not overwrite o3d_theme localStorage'
);

assertVersionedBridgeSrc(fs.readFileSync(INDEX, 'utf8'), 'editor index.html');
assertVersionedBridgeSrc(fs.readFileSync(EMBED, 'utf8'), 'editor embed fixture');

console.log('openplan3d-editor-asset-version: ok (v=' + bridgeContentVersion() + ')');
